<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;

/**
 * Answers a request for a variant file that the web server couldn't find.
 *
 * The request must name a variant some size set defines for the image as it
 * is now; anything else is not found. A missing file is generated once (other
 * requests for it wait on a lock), saved, then either sent back (when files
 * are served from the site's own domain) or redirected to (a bucket or CDN).
 * If generating or storage fails, the visitor is asked to retry shortly.
 *
 * @api
 */
final readonly class VariantRequestHandler
{
    private VariantMaker $maker;

    public function __construct(
        private VariantPath $path,
        private VariantPlanner $planner,
        private SizeSetCatalogue $catalogue,
        private DeliveryPorts $ports,
    ) {
        $this->maker = new VariantMaker($ports->generator, $ports->store, $ports->lock);
    }

    public function handle(string $requestedPath): Delivery
    {
        $requested = $this->path->parse($requestedPath);
        $source    = null === $requested ? null : $this->ports->sources->find($requested->assetUid);
        $file =
            null === $requested || null === $source
                ? null
                : $this->planner->locate($source, $requested, $this->catalogue);

        if (null === $source || null === $file) {
            return Delivery::notFound();
        }

        try {
            if (MakeResult::Failed === $this->maker->ensure($source, $file)) {
                return Delivery::unavailable();
            }

            return $this->deliver($file);
        } catch (StorageFailedException) {
            return Delivery::unavailable();
        }
    }

    /**
     * @throws StorageFailedException
     */
    private function deliver(VariantFile $file): Delivery
    {
        $url = $this->ports->store->urlFor($file->path);

        if (null !== $url && ! $this->ports->store->isOnSiteDomain()) {
            return Delivery::redirect($url);
        }

        return Delivery::content($this->ports->store->read($file->path), $file->variant->format->mimeType());
    }
}
