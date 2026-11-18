<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use Override;
use parallelogram\images\delivery\VariantStoreInterface;
use parallelogram\images\variants\VariantFile;

use function rtrim;

/**
 * Links to variants kept on the variant filesystem. When its files are served
 * from the site's own domain, pages link to them there, and a missing one
 * falls through to the variant route. Otherwise pages link to the route on
 * the site, which redirects to the bucket or CDN once the file exists.
 *
 * @api
 */
final readonly class StoredVariantLinks implements VariantLinksInterface
{
    public function __construct(
        private VariantStoreInterface $store,
        private string $siteUrl,
    ) {}

    #[Override]
    public function urlFor(VariantFile $file): string
    {
        $url = $this->store->isOnSiteDomain() ? $this->store->urlFor($file->path) : null;

        return $url ?? rtrim($this->siteUrl, characters: '/') . '/' . $file->path;
    }
}
