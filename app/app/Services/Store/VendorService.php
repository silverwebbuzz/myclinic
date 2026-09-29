<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Application;
use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;

/**
 * Store marketplace vendors: registration, login, onboarding (profile,
 * addresses, bank, KYC documents), submit-for-review and admin decisions.
 *
 * Every vendor-scoped read/write takes the vendor id from the authenticated
 * session (RequestContext), never from the request — that is the isolation
 * boundary between vendors.
 */
final class VendorService
{
    public const MAX_FAILED_LOGINS = 5;
    public const LOCK_MINUTES = 15;

    public const BUSINESS_TYPES = [
        'proprietorship' => 'Proprietorship',
        'partnership' => 'Partnership',
        'llp' => 'LLP',
        'pvt_ltd' => 'Private Limited',
        'public_ltd' => 'Public Limited',
        'other' => 'Other',
    ];

    public const DOC_TYPES = [
        'pan' => 'PAN card',
        'cancelled_cheque' => 'Cancelled cheque / bank proof',
        'gst_cert' => 'GST registration certificate',
        'fssai_license' => 'FSSAI licence',
        'ayush_license' => 'AYUSH licence',
        'medical_device_registration' => 'Medical-device registration',
        'drug_license' => 'Drug licence',
        'trade_license' => 'Trade licence / Shop & Establishment',
        'other' => 'Other',
    ];

    private const DOC_EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp'];
    private const DOC_MAX_BYTES = 5 * 1024 * 1024;
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    // ------------------------------------------------------------------
    // Registration & login
    // ------------------------------------------------------------------

    /**
     * Creates the vendor (status draft) and its owner login in one transaction.
     *
     * @param array{business_name: string, contact_name: string, email: string, phone: string, password: string} $in
     * @return array{ok: bool, error?: string, vendor_id?: int, user_id?: int}
     */
    public static function register(array $in): array
    {
        $email = strtolower(trim($in['email']));
        if (self::findUserByEmail($email) !== null) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Please log in.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $vendorId = QueryBuilder::table('store_vendors')->insert([
                'slug' => self::uniqueSlug($in['business_name']),
                'display_name' => trim($in['business_name']),
                'contact_name' => trim($in['contact_name']),
                'email' => $email,
                'phone' => self::normalizePhone($in['phone']),
                'status' => 'draft',
            ]);
            $userId = QueryBuilder::table('store_vendor_users')->insert([
                'vendor_id' => $vendorId,
                'name' => trim($in['contact_name']),
                'email' => $email,
                'password_hash' => password_hash($in['password'], PASSWORD_DEFAULT),
                'role' => 'owner',
                'status' => 'active',
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[VendorService::register] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not create your account. Please try again.'];
        }

        StoreAudit::log('vendor.register', 'vendor', $vendorId, null, ['email' => $email]);
        StoreNotifier::vendorRegistered($vendorId);

        return ['ok' => true, 'vendor_id' => $vendorId, 'user_id' => $userId];
    }

    /**
     * @return array{ok: bool, error?: string, user?: array<string, mixed>, vendor?: array<string, mixed>}
     */
    public static function attemptLogin(string $email, string $password): array
    {
        $generic = ['ok' => false, 'error' => 'Invalid email or password.'];
        $user = self::findUserByEmail(strtolower(trim($email)));
        if ($user === null) {
            // Spend the same hashing time as a real check so response timing
            // doesn't reveal which emails have accounts.
            password_verify($password, password_hash('timing-equaliser', PASSWORD_DEFAULT));

            return $generic;
        }
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            return ['ok' => false, 'error' => 'Too many failed attempts. Try again in ' . self::LOCK_MINUTES . ' minutes.'];
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            $fails = (int) $user['failed_logins'] + 1;
            $upd = ['failed_logins' => $fails];
            if ($fails >= self::MAX_FAILED_LOGINS) {
                $upd['failed_logins'] = 0;
                $upd['locked_until'] = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            }
            QueryBuilder::table('store_vendor_users')->where('id', '=', (int) $user['id'])->update($upd);

            return $generic;
        }
        if (($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'This login has been disabled. Contact support.'];
        }
        $vendor = self::find((int) $user['vendor_id']);
        if ($vendor === null || $vendor['status'] === 'closed') {
            return ['ok' => false, 'error' => 'This seller account is closed. Contact support.'];
        }

        QueryBuilder::table('store_vendor_users')->where('id', '=', (int) $user['id'])->update([
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'user' => $user, 'vendor' => $vendor];
    }

    // ------------------------------------------------------------------
    // Forgot password (store_vendor_password_resets)
    // ------------------------------------------------------------------

    public const RESET_MINUTES = 60;

    /**
     * Email a one-time reset link if the address belongs to an active seller login.
     * Always "succeeds" from the caller's view so the form never reveals which
     * emails have accounts. At most 3 links per login per hour.
     */
    public static function startPasswordReset(string $email, string $ip): void
    {
        $user = self::findUserByEmail(strtolower(trim($email)));
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return;
        }
        $vendor = self::find((int) $user['vendor_id']);
        if ($vendor === null || $vendor['status'] === 'closed') {
            return;
        }
        try {
            $recent = Database::connection()->prepare(
                'SELECT COUNT(*) FROM store_vendor_password_resets WHERE vendor_user_id = :u AND created_at >= NOW() - INTERVAL 1 HOUR'
            );
            $recent->execute(['u' => (int) $user['id']]);
            if ((int) $recent->fetchColumn() >= 3) {
                return;
            }
            $token = bin2hex(random_bytes(32));
            QueryBuilder::table('store_vendor_password_resets')->insert([
                'vendor_user_id' => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + self::RESET_MINUTES * 60),
                'created_ip' => mb_substr($ip, 0, 45) ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('[VendorService::startPasswordReset] ' . $e->getMessage());

            return;
        }
        $url = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . '/vendor/reset-password?token=' . $token;
        StoreNotifier::passwordReset($user, $url);
        StoreAudit::log('vendor_user.reset_requested', 'vendor_user', (int) $user['id']);
    }

    /** The reset row for a still-valid token, or null (unknown, used or expired). */
    public static function findResetToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        try {
            $st = Database::connection()->prepare(
                'SELECT * FROM store_vendor_password_resets WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
            );
            $st->execute(['h' => hash('sha256', $token)]);

            return $st->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function completePasswordReset(string $token, string $password, string $confirm): array
    {
        $row = self::findResetToken($token);
        if ($row === null) {
            return ['ok' => false, 'error' => 'This reset link has expired or was already used. Please request a new one.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if ($password !== $confirm) {
            return ['ok' => false, 'error' => 'Passwords do not match.'];
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Claim the token first (guarded) so two tabs can't both use it.
            $claim = $pdo->prepare('UPDATE store_vendor_password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
            $claim->execute(['id' => (int) $row['id']]);
            if ($claim->rowCount() !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This reset link was already used. Please request a new one.'];
            }
            QueryBuilder::table('store_vendor_users')->where('id', '=', (int) $row['vendor_user_id'])->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'failed_logins' => 0,
                'locked_until' => null,
            ]);
            // Any other outstanding links for this login stop working too.
            $pdo->prepare('UPDATE store_vendor_password_resets SET used_at = NOW() WHERE vendor_user_id = :u AND used_at IS NULL')
                ->execute(['u' => (int) $row['vendor_user_id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[VendorService::completePasswordReset] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not update your password. Please try again.'];
        }
        StoreAudit::log('vendor_user.password_reset', 'vendor_user', (int) $row['vendor_user_id']);

        return ['ok' => true];
    }

    public static function findUserByEmail(string $email): ?array
    {
        return QueryBuilder::table('store_vendor_users')->where('email', '=', $email)->first();
    }

    public static function findUser(int $id): ?array
    {
        return QueryBuilder::table('store_vendor_users')->where('id', '=', $id)->first();
    }

    public static function find(int $vendorId): ?array
    {
        return QueryBuilder::table('store_vendors')->where('id', '=', $vendorId)->first();
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    /** Legal/tax identity is frozen once approved — changes go through admin. */
    public static function legalFieldsLocked(array $vendor): bool
    {
        return in_array($vendor['status'], ['pending_review', 'approved', 'suspended'], true);
    }

    /**
     * @param array<string, mixed> $in
     * @param array<string, mixed>|null $logoFile $_FILES entry
     * @return array{ok: bool, error?: string}
     */
    public static function updateProfile(array $vendor, array $in, ?array $logoFile): array
    {
        $vendorId = (int) $vendor['id'];
        $data = [
            'display_name' => trim((string) ($in['display_name'] ?? '')),
            'contact_name' => trim((string) ($in['contact_name'] ?? '')),
            'phone' => self::normalizePhone((string) ($in['phone'] ?? '')),
            'description' => trim((string) ($in['description'] ?? '')) ?: null,
            'handling_days' => max(1, min(10, (int) ($in['handling_days'] ?? 2))),
            'default_return_window_days' => max(0, min(30, (int) ($in['default_return_window_days'] ?? 7))),
        ];
        if (mb_strlen($data['display_name']) < 2) {
            return ['ok' => false, 'error' => 'Store name is required.'];
        }
        if ($data['contact_name'] === '') {
            return ['ok' => false, 'error' => 'Contact person is required.'];
        }
        if (!preg_match('/^\+91[6-9]\d{9}$/', $data['phone'])) {
            return ['ok' => false, 'error' => 'Enter a valid 10-digit Indian mobile number.'];
        }

        if (!self::legalFieldsLocked($vendor)) {
            $legal = trim((string) ($in['legal_name'] ?? ''));
            $type = (string) ($in['business_type'] ?? '');
            $gstin = strtoupper(preg_replace('/\s+/', '', (string) ($in['gstin'] ?? '')));
            $pan = strtoupper(preg_replace('/\s+/', '', (string) ($in['pan'] ?? '')));

            if ($gstin !== '' && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
                return ['ok' => false, 'error' => 'GSTIN format looks wrong (15 characters, e.g. 24ABCDE1234F1Z5).'];
            }
            if ($pan !== '' && !preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $pan)) {
                return ['ok' => false, 'error' => 'PAN format looks wrong (e.g. ABCDE1234F).'];
            }
            if ($gstin !== '' && $pan !== '' && substr($gstin, 2, 10) !== $pan) {
                return ['ok' => false, 'error' => 'The PAN inside your GSTIN does not match the PAN entered.'];
            }
            if ($gstin !== '') {
                $dup = QueryBuilder::table('store_vendors')->where('gstin', '=', $gstin)->first();
                if ($dup !== null && (int) $dup['id'] !== $vendorId) {
                    return ['ok' => false, 'error' => 'This GSTIN is already registered with another seller account.'];
                }
            }
            if ($pan !== '' && !StoreCrypto::isConfigured()) {
                return ['ok' => false, 'error' => 'Secure storage is not configured on the server yet, so PAN cannot be saved. Please contact support.'];
            }

            $data['legal_name'] = $legal !== '' ? $legal : null;
            $data['business_type'] = array_key_exists($type, self::BUSINESS_TYPES) ? $type : null;
            $data['gstin'] = $gstin !== '' ? $gstin : null;
            if ($pan !== '') {
                $data['pan_enc'] = StoreCrypto::encrypt($pan);
                $data['pan_last4'] = substr($pan, -4);
            }
        }

        if ($logoFile !== null && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $logo = self::storePublicImage($logoFile, 'vendors/' . $vendorId, 'logo', self::LOGO_MAX_BYTES);
            if (!$logo['ok']) {
                return ['ok' => false, 'error' => $logo['error']];
            }
            $data['logo_path'] = $logo['path'];
        }

        QueryBuilder::table('store_vendors')->where('id', '=', $vendorId)->update($data);
        StoreAudit::log('vendor.profile_update', 'vendor', $vendorId, $vendor, $data);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Addresses (never edited in place)
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public static function addresses(int $vendorId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM store_vendor_addresses WHERE vendor_id = :v'
            . ($activeOnly ? ' AND is_active = 1' : '')
            . ' ORDER BY type, is_default DESC, id DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['v' => $vendorId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $in
     * @return array{ok: bool, error?: string}
     */
    public static function addAddress(int $vendorId, array $in): array
    {
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, ['pickup', 'return', 'registered'], true)) {
            return ['ok' => false, 'error' => 'Choose an address type.'];
        }
        $row = [
            'vendor_id' => $vendorId,
            'type' => $type,
            'contact_name' => trim((string) ($in['contact_name'] ?? '')),
            'phone' => self::normalizePhone((string) ($in['phone'] ?? '')),
            'email' => trim((string) ($in['email'] ?? '')) ?: null,
            'line1' => trim((string) ($in['line1'] ?? '')),
            'line2' => trim((string) ($in['line2'] ?? '')) ?: null,
            'city' => trim((string) ($in['city'] ?? '')),
            'state' => trim((string) ($in['state'] ?? '')),
            'pincode' => preg_replace('/\D/', '', (string) ($in['pincode'] ?? '')),
            'is_default' => 1,
            'is_active' => 1,
        ];
        foreach (['contact_name' => 'Contact name', 'line1' => 'Address line 1', 'city' => 'City', 'state' => 'State'] as $k => $label) {
            if ($row[$k] === '') {
                return ['ok' => false, 'error' => "$label is required."];
            }
        }
        if (!preg_match('/^\+91[6-9]\d{9}$/', $row['phone'])) {
            return ['ok' => false, 'error' => 'Enter a valid 10-digit mobile number for this address.'];
        }
        if (!preg_match('/^[1-9]\d{5}$/', $row['pincode'])) {
            return ['ok' => false, 'error' => 'Enter a valid 6-digit pincode.'];
        }

        $alsoReturn = $type === 'pickup' && !empty($in['same_for_return']);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($alsoReturn ? ['pickup', 'return'] : [$type] as $t) {
                // A new address of a type replaces the previous default of that type.
                $pdo->prepare('UPDATE store_vendor_addresses SET is_default = 0 WHERE vendor_id = :v AND type = :t')
                    ->execute(['v' => $vendorId, 't' => $t]);
                $row['type'] = $t;
                $id = QueryBuilder::table('store_vendor_addresses')->insert($row);
                StoreAudit::log('vendor.address_add', 'vendor_address', $id, null, $row);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[VendorService::addAddress] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save the address.'];
        }

        return ['ok' => true];
    }

    public static function deactivateAddress(int $vendorId, int $addressId): bool
    {
        $n = QueryBuilder::table('store_vendor_addresses')
            ->where('id', '=', $addressId)
            ->where('vendor_id', '=', $vendorId)
            ->update(['is_active' => 0, 'is_default' => 0]);
        if ($n > 0) {
            StoreAudit::log('vendor.address_remove', 'vendor_address', $addressId);
        }

        return $n > 0;
    }

    // ------------------------------------------------------------------
    // Bank account (encrypted)
    // ------------------------------------------------------------------

    public static function primaryBank(int $vendorId): ?array
    {
        return QueryBuilder::table('store_vendor_bank_accounts')
            ->where('vendor_id', '=', $vendorId)
            ->where('is_primary', '=', 1)
            ->first();
    }

    /**
     * @param array<string, mixed> $in
     * @return array{ok: bool, error?: string}
     */
    public static function saveBank(int $vendorId, array $in): array
    {
        if (!StoreCrypto::isConfigured()) {
            return ['ok' => false, 'error' => 'Secure storage is not configured on the server yet, so bank details cannot be saved. Please contact support.'];
        }
        $holder = trim((string) ($in['holder_name'] ?? ''));
        $acct = preg_replace('/\s+/', '', (string) ($in['account_no'] ?? ''));
        $acct2 = preg_replace('/\s+/', '', (string) ($in['account_no_confirm'] ?? ''));
        $ifsc = strtoupper(trim((string) ($in['ifsc'] ?? '')));
        $upi = trim((string) ($in['upi_id'] ?? ''));

        if ($holder === '') {
            return ['ok' => false, 'error' => 'Account holder name is required.'];
        }
        if (!preg_match('/^\d{9,18}$/', $acct)) {
            return ['ok' => false, 'error' => 'Account number should be 9–18 digits.'];
        }
        if ($acct !== $acct2) {
            return ['ok' => false, 'error' => 'Account numbers do not match.'];
        }
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            return ['ok' => false, 'error' => 'IFSC format looks wrong (e.g. HDFC0001234).'];
        }
        if ($upi !== '' && !preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $upi)) {
            return ['ok' => false, 'error' => 'UPI ID format looks wrong (e.g. name@bank).'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // New details replace the old primary; the old row is kept (inactive) for audit.
            $pdo->prepare("UPDATE store_vendor_bank_accounts SET is_primary = 0, status = 'inactive' WHERE vendor_id = :v AND is_primary = 1")
                ->execute(['v' => $vendorId]);
            $id = QueryBuilder::table('store_vendor_bank_accounts')->insert([
                'vendor_id' => $vendorId,
                'holder_name' => $holder,
                'account_no_enc' => StoreCrypto::encrypt($acct),
                'account_last4' => substr($acct, -4),
                'ifsc' => $ifsc,
                'bank_name' => trim((string) ($in['bank_name'] ?? '')) ?: null,
                'upi_id' => $upi !== '' ? $upi : null,
                'is_primary' => 1,
                'status' => 'pending',   // admin re-verifies every change
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[VendorService::saveBank] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save bank details.'];
        }
        StoreAudit::log('vendor.bank_save', 'vendor_bank', $id, null, ['last4' => substr($acct, -4), 'ifsc' => $ifsc]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // KYC documents (private storage)
    // ------------------------------------------------------------------

    /** Private, outside every web root: <repo>/storage/store_vendor_docs */
    public static function docsRoot(): string
    {
        return dirname(Application::basePath()) . '/storage/store_vendor_docs';
    }

    /** @return list<array<string, mixed>> */
    public static function documents(int $vendorId): array
    {
        return QueryBuilder::table('store_vendor_documents')
            ->where('vendor_id', '=', $vendorId)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{ok: bool, error?: string}
     */
    public static function storeDocument(int $vendorId, string $docType, array $file, string $docNumber, string $validUntil): array
    {
        if (!array_key_exists($docType, self::DOC_TYPES)) {
            return ['ok' => false, 'error' => 'Choose a document type.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Please choose a file to upload.'];
        }
        if ((int) ($file['size'] ?? 0) > self::DOC_MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 5 MB).'];
        }
        $ext = self::sniffExtension((string) $file['tmp_name'], self::DOC_EXT);
        if ($ext === null) {
            return ['ok' => false, 'error' => 'Allowed formats: PDF, JPG, PNG, WEBP.'];
        }
        if ($validUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
            return ['ok' => false, 'error' => 'Invalid expiry date.'];
        }

        $dir = self::docsRoot() . '/' . $vendorId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }
        // Defence in depth: /storage/.htaccess already denies web access, but
        // this folder must stay private even if that file is ever lost.
        $guard = self::docsRoot() . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n");
        }
        $name = $docType . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }

        $id = QueryBuilder::table('store_vendor_documents')->insert([
            'vendor_id' => $vendorId,
            'doc_type' => $docType,
            'doc_number' => trim($docNumber) !== '' ? substr(trim($docNumber), 0, 80) : null,
            'file_path' => $vendorId . '/' . $name,
            'original_name' => substr((string) ($file['name'] ?? $name), 0, 255),
            'status' => 'pending',
            'valid_until' => $validUntil !== '' ? $validUntil : null,
        ]);
        StoreAudit::log('vendor.document_upload', 'vendor_document', $id, null, ['doc_type' => $docType]);

        return ['ok' => true];
    }

    /**
     * Streams a document inline. Caller must have already authorised access.
     */
    public static function documentResponse(array $doc): \App\Http\Response
    {
        $rel = (string) $doc['file_path'];
        $root = realpath(self::docsRoot());
        $abs = $root !== false ? realpath($root . '/' . $rel) : false;
        if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return \App\Http\Response::html('File not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        return (new \App\Http\Response((string) file_get_contents($abs), 200, [
            'Content-Type' => self::DOC_EXT[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) ($doc['original_name'] ?? basename($abs))) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]));
    }

    // ------------------------------------------------------------------
    // Onboarding checklist + submit
    // ------------------------------------------------------------------

    /**
     * What's still missing before the vendor can submit for review.
     *
     * @return array{items: list<array{key: string, label: string, done: bool, href: string}>, complete: bool}
     */
    public static function checklist(array $vendor): array
    {
        $vendorId = (int) $vendor['id'];
        $addresses = self::addresses($vendorId);
        $hasType = static fn (string $t): bool => (bool) array_filter($addresses, static fn ($a) => $a['type'] === $t);
        $docTypes = array_column(array_filter(self::documents($vendorId), static fn ($d) => $d['status'] !== 'rejected'), 'doc_type');
        $bank = self::primaryBank($vendorId);

        $items = [
            ['key' => 'profile', 'label' => 'Business details (legal name, business type, PAN)',
                'done' => !empty($vendor['legal_name']) && !empty($vendor['business_type']) && !empty($vendor['pan_last4']),
                'href' => '/vendor/profile'],
            ['key' => 'pickup', 'label' => 'Pickup address (where couriers collect orders)', 'done' => $hasType('pickup'), 'href' => '/vendor/addresses'],
            ['key' => 'return', 'label' => 'Return address', 'done' => $hasType('return'), 'href' => '/vendor/addresses'],
            ['key' => 'bank', 'label' => 'Bank account for payouts', 'done' => $bank !== null, 'href' => '/vendor/bank'],
            ['key' => 'doc_pan', 'label' => 'Upload PAN card', 'done' => in_array('pan', $docTypes, true), 'href' => '/vendor/documents'],
            ['key' => 'doc_cheque', 'label' => 'Upload cancelled cheque / bank proof', 'done' => in_array('cancelled_cheque', $docTypes, true), 'href' => '/vendor/documents'],
        ];
        // Sellers on an e-commerce marketplace generally must be GST-registered (VERIFY WITH CA);
        // admin can relax this in settings, then those sellers issue a bill of supply without GST.
        if (StoreSettings::get('store_require_gstin', '1') === '1') {
            $items[] = ['key' => 'gstin', 'label' => 'GSTIN (needed to sell on the marketplace; printed on your invoices)',
                'done' => !empty($vendor['gstin']), 'href' => '/vendor/profile'];
        }
        $items[] = ['key' => 'terms', 'label' => 'Read and accept the seller rules & terms',
            'done' => !StorePolicyService::needsAcceptance($vendorId), 'href' => '/vendor/terms'];
        if (!empty($vendor['gstin'])) {
            $items[] = ['key' => 'doc_gst', 'label' => 'Upload GST certificate', 'done' => in_array('gst_cert', $docTypes, true), 'href' => '/vendor/documents'];
        }

        return ['items' => $items, 'complete' => !in_array(false, array_column($items, 'done'), true)];
    }

    /** @return array{ok: bool, error?: string} */
    public static function submitForReview(array $vendor): array
    {
        if (!in_array($vendor['status'], ['draft', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Your account is already ' . str_replace('_', ' ', (string) $vendor['status']) . '.'];
        }
        if (!self::checklist($vendor)['complete']) {
            return ['ok' => false, 'error' => 'Please complete every step in the checklist first.'];
        }
        QueryBuilder::table('store_vendors')->where('id', '=', (int) $vendor['id'])->update([
            'status' => 'pending_review',
            'status_reason' => null,
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        StoreAudit::log('vendor.submit', 'vendor', (int) $vendor['id'], ['status' => $vendor['status']], ['status' => 'pending_review']);
        StoreNotifier::vendorSubmitted((int) $vendor['id']);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    /**
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>}
     */
    public static function adminList(string $status, string $q): array
    {
        $pdo = Database::connection();
        $counts = [];
        foreach ($pdo->query('SELECT status, COUNT(*) n FROM store_vendors GROUP BY status')->fetchAll() as $r) {
            $counts[(string) $r['status']] = (int) $r['n'];
        }

        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'v.status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            // Distinct placeholders — native prepares can't reuse a named one.
            $where[] = '(v.display_name LIKE :q1 OR v.legal_name LIKE :q2 OR v.email LIKE :q3 OR v.gstin LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[$k] = '%' . $q . '%';
            }
        }
        $sql = 'SELECT v.*,
                       (SELECT COUNT(*) FROM store_vendor_documents d WHERE d.vendor_id = v.id AND d.status = \'pending\') AS pending_docs
                  FROM store_vendors v'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY FIELD(v.status, \'pending_review\') DESC, v.submitted_at DESC, v.id DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'counts' => $counts];
    }

    public static function pendingReviewCount(): int
    {
        try {
            return (int) Database::connection()
                ->query("SELECT COUNT(*) FROM store_vendors WHERE status = 'pending_review'")
                ->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function adminSetStatus(int $vendorId, string $action, string $reason, int $adminId): array
    {
        $vendor = self::find($vendorId);
        if ($vendor === null) {
            return ['ok' => false, 'error' => 'Seller not found.'];
        }
        $from = (string) $vendor['status'];
        $map = [
            'approve' => [['pending_review', 'rejected', 'suspended'], 'approved'],
            'reject' => [['pending_review'], 'rejected'],
            'suspend' => [['approved'], 'suspended'],
            'reactivate' => [['suspended'], 'approved'],
            'close' => [['draft', 'pending_review', 'approved', 'rejected', 'suspended'], 'closed'],
        ];
        if (!isset($map[$action])) {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }
        [$allowedFrom, $to] = $map[$action];
        if (!in_array($from, $allowedFrom, true)) {
            return ['ok' => false, 'error' => "Can't $action a seller who is " . str_replace('_', ' ', $from) . '.'];
        }
        if (in_array($action, ['reject', 'suspend', 'close'], true) && trim($reason) === '') {
            return ['ok' => false, 'error' => 'Please give a reason (the seller will see it).'];
        }

        $upd = ['status' => $to, 'status_reason' => trim($reason) !== '' ? trim($reason) : null];
        if ($to === 'approved' && empty($vendor['approved_at'])) {
            $upd['approved_by'] = $adminId;
            $upd['approved_at'] = date('Y-m-d H:i:s');
        }
        QueryBuilder::table('store_vendors')->where('id', '=', $vendorId)->update($upd);
        StoreAudit::log('vendor.' . $action, 'vendor', $vendorId, ['status' => $from], $upd);
        StoreNotifier::vendorStatusChanged($vendorId, $action, $reason);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function adminReviewDocument(int $docId, string $decision, string $reason, int $adminId): array
    {
        $doc = QueryBuilder::table('store_vendor_documents')->where('id', '=', $docId)->first();
        if ($doc === null) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Unknown decision.'];
        }
        if ($decision === 'rejected' && trim($reason) === '') {
            return ['ok' => false, 'error' => 'Give a reason for rejecting the document.'];
        }
        $upd = [
            'status' => $decision,
            'reject_reason' => $decision === 'rejected' ? trim($reason) : null,
            'reviewed_by' => $adminId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ];
        QueryBuilder::table('store_vendor_documents')->where('id', '=', $docId)->update($upd);
        StoreAudit::log('vendor_document.' . $decision, 'vendor_document', $docId, ['status' => $doc['status']], $upd);
        if ($decision === 'rejected') {
            StoreNotifier::documentRejected($docId);
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function adminVerifyBank(int $bankId, string $decision): array
    {
        if (!in_array($decision, ['verified', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Unknown decision.'];
        }
        $upd = ['status' => $decision, 'verified_at' => $decision === 'verified' ? date('Y-m-d H:i:s') : null];
        $n = QueryBuilder::table('store_vendor_bank_accounts')->where('id', '=', $bankId)->update($upd);
        if ($n > 0) {
            StoreAudit::log('vendor_bank.' . $decision, 'vendor_bank', $bankId, null, $upd);
        }

        return ['ok' => $n > 0];
    }

    /** Full account number for an admin who explicitly asks to reveal it (audited). */
    public static function adminRevealAccount(int $bankId): ?string
    {
        $bank = QueryBuilder::table('store_vendor_bank_accounts')->where('id', '=', $bankId)->first();
        if ($bank === null) {
            return null;
        }
        StoreAudit::log('vendor_bank.reveal', 'vendor_bank', $bankId);

        return StoreCrypto::decrypt($bank['account_no_enc'] ?? null);
    }

    /** @return array{ok: bool, error?: string, password?: string} */
    public static function adminResetPassword(int $vendorId): array
    {
        $owner = QueryBuilder::table('store_vendor_users')
            ->where('vendor_id', '=', $vendorId)
            ->where('role', '=', 'owner')
            ->first();
        if ($owner === null) {
            return ['ok' => false, 'error' => 'No owner login found for this seller.'];
        }
        $temp = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 12);
        QueryBuilder::table('store_vendor_users')->where('id', '=', (int) $owner['id'])->update([
            'password_hash' => password_hash($temp, PASSWORD_DEFAULT),
            'failed_logins' => 0,
            'locked_until' => null,
        ]);
        StoreAudit::log('vendor_user.password_reset', 'vendor_user', (int) $owner['id']);

        return ['ok' => true, 'password' => $temp];
    }

    public static function adminToggleFeatured(int $vendorId): void
    {
        Database::connection()->prepare('UPDATE store_vendors SET is_featured = 1 - is_featured WHERE id = :id')
            ->execute(['id' => $vendorId]);
        StoreAudit::log('vendor.toggle_featured', 'vendor', $vendorId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public static function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return strlen($d) === 10 ? '+91' . $d : $raw;
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = str_replace(['&', "'"], ['and', ''], $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-');
    }

    private static function uniqueSlug(string $name): string
    {
        $base = self::slugify($name) ?: 'seller';
        $base = substr($base, 0, 100);
        $slug = $base;
        $i = 2;
        while (QueryBuilder::table('store_vendors')->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Content-sniffed extension (never trust the uploaded file name).
     *
     * @param array<string, string> $allowed ext => mime
     */
    private static function sniffExtension(string $tmp, array $allowed): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ext = array_search($mime, $allowed, true);

        return $ext === false ? null : (string) $ext;
    }

    /**
     * Public images (logo, later product photos) live under app/public/uploads/store/
     * and are served from the portal domain.
     *
     * @param array<string, mixed> $file
     * @return array{ok: bool, error?: string, path?: string}
     */
    public static function storePublicImage(array $file, string $subdir, string $prefix, int $maxBytes): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Image upload failed.'];
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'Image too large (max ' . (int) ($maxBytes / 1048576) . ' MB).'];
        }
        $ext = self::sniffExtension((string) $file['tmp_name'], ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']);
        if ($ext === null) {
            return ['ok' => false, 'error' => 'Images must be JPG, PNG or WEBP.'];
        }
        $rel = '/uploads/store/' . trim($subdir, '/');
        $dir = Application::basePath() . '/public' . $rel;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Image upload failed.'];
        }

        return ['ok' => true, 'path' => $rel . '/' . $name];
    }

    /** @return list<array<string, mixed>> */
    public static function adminAuditTrail(int $vendorId, int $limit = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM store_audit_log
              WHERE (entity_type = 'vendor' AND entity_id = :v1)
                 OR (entity_type = 'vendor_document'
                     AND entity_id IN (SELECT id FROM store_vendor_documents WHERE vendor_id = :v2))
                 OR (entity_type = 'vendor_bank'
                     AND entity_id IN (SELECT id FROM store_vendor_bank_accounts WHERE vendor_id = :v3))
                 OR (entity_type = 'vendor_address'
                     AND entity_id IN (SELECT id FROM store_vendor_addresses WHERE vendor_id = :v4))
              ORDER BY id DESC LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute(['v1' => $vendorId, 'v2' => $vendorId, 'v3' => $vendorId, 'v4' => $vendorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
