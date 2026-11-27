<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\PatientIdentityAuthService;
use App\Services\RecaptchaService;
use App\Services\Wellness\CreatorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Creator Studio — app.eclinicpro.com/creator (Wellness Hub, Phase 1).
 *
 * The creator always comes from RequestContext (CreatorAuthMiddleware), never
 * from the URL or form, so a creator can only touch their own rows.
 */
final class CreatorStudioController
{
    // ---- Login / logout ------------------------------------------------------

    /** GET /creator/login — phone OTP, same account as eclinicpro.com/patient. */
    public function login(Request $request): Response
    {
        if (PatientIdentityAuthService::current() !== null) {
            return Response::redirect('/creator/dashboard');
        }

        return Response::html(View::render('creator/login', [
            'otpApi' => CreatorService::siteUrl('/api/patient_auth.php'),
            'captchaSiteKey' => RecaptchaService::enabled() ? RecaptchaService::siteKey() : '',
            'mode' => ($request->query['mode'] ?? '') === 'signup' ? 'signup' : 'signin',
        ]));
    }

    /** POST /creator/logout — ends the shared patient session too. */
    public function logout(Request $request): Response
    {
        PatientIdentityAuthService::logout();

        return Response::redirect('/creator/login');
    }

    // ---- Join ----------------------------------------------------------------

    public function joinForm(Request $request): Response
    {
        $identity = $this->identity();

        return $this->render('creator/join', [
            'form' => [
                'display_name' => (string) ($identity['name'] ?? '') !== 'Patient' ? (string) ($identity['name'] ?? '') : '',
                'email' => (string) ($identity['email'] ?? ''),
                'content_types' => [],
            ],
        ]);
    }

    public function join(Request $request): Response
    {
        $res = CreatorService::join($this->identity(), $request->post);
        if (!$res['ok']) {
            return $this->render('creator/join', ['form' => $request->post, 'flashErr' => $res['error']]);
        }
        SessionFlash::put('creator_ok', 'Welcome! Your application is with our team. Add proof on the Permissions page to help us approve you faster.');

        return Response::redirect('/creator/dashboard');
    }

    // ---- Dashboard / content ---------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $c = $this->creator();

        return $this->render('creator/dashboard', [
            'permissions' => CreatorService::permissions((int) $c['id']),
            'tipCounts' => CreatorService::tipCounts($c),
        ]);
    }

    public function content(Request $request): Response
    {
        $c = $this->creator();

        return $this->render('creator/content', [
            'tips' => CreatorService::tips($c),
            'approved' => CreatorService::approvedTypes((int) $c['id']),
        ]);
    }

    // ---- Profile -------------------------------------------------------------

    public function profile(Request $request): Response
    {
        return $this->render('creator/profile', ['form' => $this->creator()]);
    }

    public function saveProfile(Request $request): Response
    {
        $avatar = $_FILES['avatar'] ?? null;
        $res = CreatorService::saveProfile($this->creator(), $request->post, is_array($avatar) ? $avatar : null);
        if (!$res['ok']) {
            return $this->render('creator/profile', ['form' => $request->post + $this->creator(), 'flashErr' => $res['error']]);
        }
        SessionFlash::put('creator_ok', 'Profile saved.');

        return Response::redirect('/creator/profile');
    }

    public function avatar(Request $request): Response
    {
        return CreatorService::avatarResponse($this->creator());
    }

    // ---- Permissions ---------------------------------------------------------

    public function permissions(Request $request): Response
    {
        $c = $this->creator();

        return $this->render('creator/permissions', [
            'permissions' => CreatorService::permissions((int) $c['id']),
            'documents' => array_values(array_filter(CreatorService::documents((int) $c['id']), static fn ($d) => $d['purpose'] === 'permission')),
        ]);
    }

    public function applyPermission(Request $request): Response
    {
        $files = $_FILES['proof'] ?? [];
        $res = CreatorService::applyPermission(
            $this->creator(),
            (string) ($request->post['content_type'] ?? ''),
            (string) ($request->post['applicant_note'] ?? ''),
            is_array($files) ? $files : [],
            (string) ($request->post['doc_type'] ?? 'other'),
        );
        $this->flash($res, 'Sent for review. We’ll email you when it’s decided.');

        return Response::redirect('/creator/permissions');
    }

    public function addProof(Request $request): Response
    {
        $file = $_FILES['proof_one'] ?? [];
        $res = CreatorService::addPermissionProof(
            $this->creator(),
            (string) ($request->post['content_type'] ?? ''),
            (string) ($request->post['doc_type'] ?? 'other'),
            is_array($file) ? $file : [],
        );
        $this->flash($res, 'File added to your application.');

        return Response::redirect('/creator/permissions');
    }

    // ---- Verification badge --------------------------------------------------

    public function verification(Request $request): Response
    {
        $c = $this->creator();

        return $this->render('creator/verification', [
            'kind' => CreatorService::badgeKind($c),
            'documents' => array_values(array_filter(CreatorService::documents((int) $c['id']), static fn ($d) => $d['purpose'] === 'verification')),
            'form' => $c,
        ]);
    }

    public function requestVerification(Request $request): Response
    {
        $files = $_FILES['doc'] ?? [];
        $res = CreatorService::requestVerification($this->creator(), $request->post, is_array($files) ? $files : []);
        $this->flash($res, 'Documents sent. Our team checks them against the official register — usually within 2–3 working days.');

        return Response::redirect('/creator/verification');
    }

    /** GET /creator/documents/{id} — own documents only. */
    public function document(Request $request, string $id): Response
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_creator_documents WHERE id = :id AND creator_id = :c LIMIT 1');
        $st->execute(['id' => (int) $id, 'c' => (int) $this->creator()['id']]);
        $doc = $st->fetch(\PDO::FETCH_ASSOC);

        return $doc ? CreatorService::documentResponse($doc) : Response::html('Not found', 404);
    }

    // ---- Helpers -------------------------------------------------------------

    /** @return array<string,mixed> */
    private function identity(): array
    {
        return RequestContext::creatorIdentity() ?? throw new \RuntimeException('Creator identity missing.');
    }

    /** @return array<string,mixed> */
    private function creator(): array
    {
        return RequestContext::creator() ?? throw new \RuntimeException('Creator context missing.');
    }

    /** @param array{ok:bool,error?:string} $res */
    private function flash(array $res, string $success): void
    {
        SessionFlash::put($res['ok'] ? 'creator_ok' : 'creator_err', $res['ok'] ? $success : ($res['error'] ?? 'Something went wrong.'));
    }

    /** @param array<string,mixed> $data */
    private function render(string $view, array $data = []): Response
    {
        $creator = RequestContext::creator();
        if ($creator !== null) {
            $creator = CreatorService::find((int) $creator['id']) ?? $creator;   // reflect a save in this cycle
        }

        return Response::html(View::render($view, $data + [
            'creator' => $creator,
            'identity' => RequestContext::creatorIdentity(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('creator_ok'),
            'flashErr' => SessionFlash::pull('creator_err'),
        ]));
    }
}
