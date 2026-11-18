<?php

declare(strict_types=1);

namespace parallelogram\images\utilities;

use Craft;
use craft\base\Utility;
use craft\web\View;
use Override;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;

/**
 * Utilities → Parallelogram Images: queues making missing variants, or
 * removing unused ones.
 *
 * @api
 */
final class WarmUtility extends Utility
{
    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    #[Override]
    public static function contentHtml(): string
    {
        /** @var View $view Craft's view is always its web view, in console requests too. */
        $view = Craft::$app->getView();

        return $view->renderTemplate('p-images/_utility');
    }

    #[Override]
    public static function displayName(): string
    {
        return Craft::t('p-images', message: 'Parallelogram Images');
    }

    #[Override]
    public static function icon(): ?string
    {
        return 'image';
    }

    #[Override]
    public static function id(): string
    {
        return 'p-images-warm';
    }
}
