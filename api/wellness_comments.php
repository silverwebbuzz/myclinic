<?php
// =====================================================================
// api/wellness_comments.php — comments & Q/A on Wellness Hub posts (Phase 5).
//
//   POST JSON {action:"post",   item_type:"tip"|"event", item_id, kind:"comment"|"question", body, parent_id?}
//   POST JSON {action:"report", id, reason}
//   POST JSON {action:"delete", id}          (your own)
//   POST JSON {action:"hide",   id}          (the post's own creator)
//
// Signed-in patients only (401 → the page opens the sign-in modal). JSON +
// same-origin only. Rules live in partials/wellness_comments.php.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../partials/wellness_comments.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = static function (int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $fail(405, 'method_not_allowed');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
    $fail(403, 'bad_origin');
}
$me = ecp_patient_current();
if (!$me) {
    $fail(401, 'login_required');
}
$db = ecp_db();
if (!$db || !wh_c_enabled($db)) {
    $fail(503, 'not_available');
}
$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) {
    $fail(400, 'bad_request');
}
$identityId = (int) $me['id'];

switch ((string) ($in['action'] ?? '')) {
    case 'post':
        $type = (string) ($in['item_type'] ?? '');
        if (!in_array($type, ['tip', 'event'], true)) {
            $fail(400, 'bad_request');
        }
        $res = wh_c_post($db, $identityId, $type, (int) ($in['item_id'] ?? 0), (string) ($in['kind'] ?? 'comment'),
            (string) ($in['body'] ?? ''), !empty($in['parent_id']) ? (int) $in['parent_id'] : null);
        if (!$res['ok']) {
            echo json_encode(['ok' => false, 'error' => 'invalid', 'message' => $res['error']]);
            exit;
        }
        wh_comments_send_mail($res['notify'] ?? []);
        echo json_encode(['ok' => true, 'id' => $res['id'], 'held' => $res['status'] === 'held']);
        exit;

    case 'report':
        $res = wh_c_report($db, (int) ($in['id'] ?? 0), $identityId, (string) ($in['reason'] ?? ''));
        echo json_encode($res['ok'] ? ['ok' => true] : ['ok' => false, 'error' => 'invalid', 'message' => $res['error']]);
        exit;

    case 'delete':
        echo json_encode(['ok' => wh_c_delete_own($db, (int) ($in['id'] ?? 0), $identityId)]);
        exit;

    case 'hide':
        echo json_encode(['ok' => wh_c_hide_by_owner($db, (int) ($in['id'] ?? 0), $identityId)]);
        exit;
}
$fail(400, 'unknown_action');

/** @param list<array{type:string,email:string,name:string,title:string,path:string,excerpt:string}> $list */
function wh_comments_send_mail(array $list): void
{
    if (!$list) {
        return;
    }
    try {
        require_once __DIR__ . '/../partials/mailer.php';
        foreach ($list as $n) {
            $url = ecp_site_url($n['path']);
            if ($n['type'] === 'new_question') {
                $subject = 'New question on “' . $n['title'] . '”';
                $html = '<p>Hi ' . e($n['name']) . ',</p><p>Someone asked a question on your post <strong>' . e($n['title']) . '</strong>:</p>'
                    . '<blockquote style="border-left:3px solid #0F9B6E;margin:12px 0;padding:4px 12px;color:#333;">' . nl2br(e($n['excerpt'])) . '</blockquote>'
                    . '<p>Your answer shows as <strong>“Creator’s answer”</strong>. Please keep it general — not personal medical advice.</p>'
                    . '<p><a href="' . e($url) . '">Answer on the page</a> · or in <a href="' . e(ecp_portal_url('/creator/questions')) . '">Creator Studio → Questions</a></p>';
                ecp_send_mail($n['email'], $subject, ecp_email_template('You have a new question', $html));
            } elseif ($n['type'] === 'answered') {
                $subject = 'Your question was answered — ' . $n['title'];
                $html = '<p>Hi ' . e($n['name']) . ',</p><p>There’s a reply to your question on <strong>' . e($n['title']) . '</strong>:</p>'
                    . '<blockquote style="border-left:3px solid #0F9B6E;margin:12px 0;padding:4px 12px;color:#333;">' . nl2br(e($n['excerpt'])) . '</blockquote>'
                    . '<p><a href="' . e($url) . '">See the full answer</a></p>'
                    . '<p style="color:#6E6E73;font-size:13px;">Answers on the Wellness Hub are general information, not medical advice. For your own symptoms, please consult a doctor.</p>';
                ecp_send_mail($n['email'], $subject, ecp_email_template('Your question was answered', $html));
            }
        }
    } catch (Throwable $e) {
        error_log('[api/wellness_comments mail] ' . $e->getMessage());
    }
}
