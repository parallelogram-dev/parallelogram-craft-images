<?php

declare(strict_types=1);

namespace putyourlightson\blitz;

use putyourlightson\blitz\services\RefreshCacheService;

/**
 * A recording stand-in for the parts of Blitz this plugin calls. Blitz isn't
 * a dependency, so tests that need it present load this file, in a process of
 * their own because it declares classes under Blitz's namespace.
 */
final class Blitz
{
    public static ?Blitz $plugin = null;

    public RefreshCacheService $refreshCache;

    public function __construct()
    {
        $this->refreshCache = new RefreshCacheService();
    }
}
