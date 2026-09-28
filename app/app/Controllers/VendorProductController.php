<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\CatalogService;
use App\Services\Store\ProductService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Seller product management (/vendor/products/*). Every lookup is scoped to the
 * logged-in seller via ProductService::findForVendor().
 */
final class VendorProductController
{
    private const STATUS_TABS = ['', 'draft', 'pending_review', 'live', 'rejected', 'disabled', 'archived'];

    public function index(Request $request): Response
    {
        $vendor = $this->vendor();
        $status = (string) ($request->query['status'] ?? '');
        if (!in_array($status, self::STATUS_TABS, true)) {
            $status = '';
        }

        return $this->render('store_vendor/products', [
            'products' => ProductService::listForVendor((int) $vendor['id'], $status),
            'counts' => ProductService::vendorCounts((int) $vendor['id']),
            'status' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        $vendor = $this->vendor();
        if ($vendor['status'] !== 'approved') {
            SessionFlash::put('store_err', 'You can add products once your seller account is approved.');

            return Response::redirect('/vendor/dashboard');
        }

        return $this->renderForm(null, []);
    }

    public function store(Request $request): Response
    {
        $result = ProductService::saveFromVendor($this->vendor(), null, $request->post);
        if (!$result['ok']) {
            return $this->renderForm(null, $request->post, $result['error'] ?? 'Could not save.');
        }
        SessionFlash::put('store_ok', 'Draft saved. Now add photos, then submit for review.');

        return Response::redirect('/vendor/products/' . (int) $result['id'] . '#photos');
    }

    public function edit(Request $request, string $id): Response
    {
        $product = $this->product((int) $id);

        return $product === null ? $this->notFound() : $this->renderForm(ProductService::load((int) $product['id']), []);
    }

    public function update(Request $request, string $id): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        $result = ProductService::saveFromVendor($this->vendor(), $product, $request->post);
        if (!$result['ok']) {
            return $this->renderForm(ProductService::load((int) $product['id']), $request->post, $result['error'] ?? 'Could not save.');
        }
        SessionFlash::put('store_ok', !empty($result['sent_to_review'])
            ? 'Saved. Because the name, category or description changed, the product is back under review and hidden until approved.'
            : 'Saved.');

        return Response::redirect('/vendor/products/' . (int) $product['id']);
    }

    public function uploadImages(Request $request, string $id): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        $files = $_FILES['images'] ?? [];
        $result = ProductService::addImages($product, is_array($files) ? $files : []);
        if ($result['ok']) {
            SessionFlash::put('store_ok', $result['added'] . ' photo(s) added.' . (!empty($result['error']) ? ' Skipped: ' . $result['error'] : ''));
        } else {
            SessionFlash::put('store_err', $result['error'] ?? 'Upload failed.');
        }

        return Response::redirect('/vendor/products/' . (int) $product['id'] . '#photos');
    }

    public function deleteImage(Request $request, string $id, string $imageId): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        ProductService::deleteImage($product, (int) $imageId)
            ? SessionFlash::put('store_ok', 'Photo removed.')
            : SessionFlash::put('store_err', 'Photo not found.');

        return Response::redirect('/vendor/products/' . (int) $product['id'] . '#photos');
    }

    public function coverImage(Request $request, string $id, string $imageId): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        ProductService::makeCover($product, (int) $imageId);

        return Response::redirect('/vendor/products/' . (int) $product['id'] . '#photos');
    }

    public function submit(Request $request, string $id): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        $result = ProductService::submit($this->vendor(), $product);
        if ($result['ok']) {
            SessionFlash::put('store_ok', !empty($result['live'])
                ? 'Published! Your product is live on the store.'
                : 'Submitted for review. We usually review products within 1 working day.');
        } else {
            SessionFlash::put('store_err', $result['error'] ?? 'Could not submit.');
        }

        return Response::redirect('/vendor/products/' . (int) $product['id']);
    }

    public function archive(Request $request, string $id): Response
    {
        $product = $this->product((int) $id);
        if ($product === null) {
            return $this->notFound();
        }
        $archive = ($request->post['do'] ?? 'archive') === 'archive';
        ProductService::setArchived($product, $archive)
            ? SessionFlash::put('store_ok', $archive ? 'Product archived (hidden from customers).' : 'Product restored as a draft.')
            : SessionFlash::put('store_err', 'That action is not available for this product.');

        return Response::redirect('/vendor/products/' . (int) $product['id']);
    }

    // ---- Helpers ------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $product loaded product (null = new)
     * @param array<string, mixed> $old re-populate after a validation error
     */
    private function renderForm(?array $product, array $old, ?string $error = null): Response
    {
        $vendor = $this->vendor();
        $licences = CatalogService::vendorLicences((int) $vendor['id']);
        $tree = CatalogService::tree();
        // Pre-compute which subcategories this seller may pick, and why not.
        foreach ($tree as &$dept) {
            foreach ($dept['subs'] as &$sub) {
                $sub['block_reason'] = CatalogService::listingBlockReason($sub, $licences);
            }
            unset($sub);
        }
        unset($dept);

        return $this->render('store_vendor/product_form', [
            'product' => $product,
            'old' => $old,
            'formError' => $error,
            'tree' => $tree,
            'brands' => CatalogService::brands(),
            'concerns' => CatalogService::concerns(),
            'licences' => $licences,
            'problems' => $product !== null ? ProductService::submitProblems($vendor, $product) : [],
        ]);
    }

    private function product(int $id): ?array
    {
        return ProductService::findForVendor($id, (int) $this->vendor()['id']);
    }

    private function notFound(): Response
    {
        return Response::html('Product not found', 404);
    }

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'vendor' => VendorService::find((int) $this->vendor()['id']) ?? $this->vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
