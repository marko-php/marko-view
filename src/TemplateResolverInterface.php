<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\View\Exceptions\InvalidTemplateException;
use Marko\View\Exceptions\TemplateNotFoundException;

interface TemplateResolverInterface
{
    /**
     * Resolve a template name to its absolute file path.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @return string Absolute path to template file
     * @throws InvalidTemplateException|TemplateNotFoundException
     */
    public function resolve(string $template): string;

    /**
     * Get all paths that were searched for a template.
     * Useful for debugging TemplateNotFoundException.
     *
     * @param string $template Template name
     * @return array<string> List of searched paths
     * @throws InvalidTemplateException When the template name is unsafe
     */
    public function getSearchedPaths(string $template): array;
}
