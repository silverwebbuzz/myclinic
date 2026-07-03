<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\WordPressDoctorService;
use App\Services\WordPressService;
use App\Support\Layout;
use App\Support\WordPressSettings;

final class BlogController
{
    public function index(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $link = WordPressDoctorService::linkForUser((int) ($user['id'] ?? 0));

        if ($link === null) {
            return Response::html(Layout::page('blogs/no_access', [
                'wpConfigured' => WordPressSettings::isConfigured(),
            ], 'Blogs'));
        }

        $posts = WordPressService::listPostsForAuthor((int) $link['wp_user_id']);

        return Response::html(Layout::page('blogs/index', [
            'posts' => $posts,
            'link' => $link,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ], 'Blogs'));
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->form($request, (int) $id);
    }

    private function form(Request $request, ?int $postId): Response
    {
        $user = RequestContext::user() ?? [];
        $link = WordPressDoctorService::linkForUser((int) ($user['id'] ?? 0));
        if ($link === null) {
            return Response::redirect('/blogs?error=' . rawurlencode('WordPress access not enabled for your account.'));
        }

        $post = null;
        if ($postId !== null) {
            $post = WordPressService::getPost($postId);
            if ($post === null || (int) ($post['author'] ?? 0) !== (int) $link['wp_user_id']) {
                return Response::redirect('/blogs?error=' . rawurlencode('Post not found.'));
            }
        }

        return Response::html(Layout::page('blogs/form', [
            'post' => $post,
            'link' => $link,
        ], $post ? 'Edit post' : 'New post'));
    }

    public function save(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $link = WordPressDoctorService::linkForUser((int) ($user['id'] ?? 0));
        if ($link === null) {
            return Response::redirect('/blogs?error=' . rawurlencode('WordPress access not enabled.'));
        }

        $postId = (int) ($request->post['post_id'] ?? 0);
        $title = trim((string) ($request->post['title'] ?? ''));
        $content = trim((string) ($request->post['content'] ?? ''));
        $excerpt = trim(strip_tags(html_entity_decode(trim((string) ($request->post['excerpt'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $status = (string) ($request->post['status'] ?? 'draft');
        $status = in_array($status, ['draft', 'publish', 'pending'], true) ? $status : 'draft';

        if ($title === '' || $content === '') {
            $redirect = $postId > 0 ? '/blogs/' . $postId . '/edit' : '/blogs/new';
            return Response::redirect($redirect . '?error=' . rawurlencode('Title and content are required.'));
        }

        $payload = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'status' => $status,
        ];

        $authorId = (int) $link['wp_user_id'];
        if ($postId > 0) {
            $ok = WordPressService::updatePost($postId, $authorId, $payload);
            AuditService::log($request, 'UPDATE', 'wordpress_posts', $postId);
            if (!$ok) {
                return Response::redirect('/blogs/' . $postId . '/edit?error=' . rawurlencode('Could not update post.'));
            }
        } else {
            $created = WordPressService::createPost($authorId, $payload);
            AuditService::log($request, 'INSERT', 'wordpress_posts', (int) ($created['id'] ?? 0));
            if ($created === null) {
                return Response::redirect('/blogs/new?error=' . rawurlencode('Could not create post.'));
            }
            $postId = (int) $created['id'];
        }

        $msg = $status === 'publish' ? 'post_published' : 'post_saved';

        return Response::redirect('/blogs?message=' . $msg);
    }

    public function delete(Request $request, string $id): Response
    {
        $user = RequestContext::user() ?? [];
        $link = WordPressDoctorService::linkForUser((int) ($user['id'] ?? 0));
        if ($link === null) {
            return Response::redirect('/blogs?error=' . rawurlencode('WordPress access not enabled.'));
        }

        $postId = (int) $id;
        $ok = WordPressService::deletePost($postId, (int) $link['wp_user_id']);
        AuditService::log($request, 'DELETE', 'wordpress_posts', $postId);

        if (!$ok) {
            return Response::redirect('/blogs?error=' . rawurlencode('Could not delete post.'));
        }

        return Response::redirect('/blogs?message=post_deleted');
    }

    public function publish(Request $request, string $id): Response
    {
        $user = RequestContext::user() ?? [];
        $link = WordPressDoctorService::linkForUser((int) ($user['id'] ?? 0));
        if ($link === null) {
            return Response::redirect('/blogs?error=' . rawurlencode('WordPress access not enabled.'));
        }

        $postId = (int) $id;
        $ok = WordPressService::updatePost($postId, (int) $link['wp_user_id'], ['status' => 'publish']);
        AuditService::log($request, 'UPDATE', 'wordpress_posts', $postId);

        if (!$ok) {
            return Response::redirect('/blogs?error=' . rawurlencode('Could not publish post.'));
        }

        return Response::redirect('/blogs?message=post_published');
    }
}
