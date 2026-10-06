<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Exceptions\InvalidTemplateException;
use Marko\View\Exceptions\TemplateNotFoundException;

readonly class ModuleTemplateResolver implements TemplateResolverInterface
{
    private const string VIEWS_DIRECTORY = '/resources/views/';

    private const string MODULE_NAME_PATTERN = '/^[A-Za-z0-9_.\-]+(?:\/[A-Za-z0-9_.\-]+)?$/';

    private const string PATH_SEGMENT_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ViewConfig $viewConfig,
    ) {}

    /**
     * @throws InvalidTemplateException|TemplateNotFoundException
     */
    public function resolve(
        string $template,
    ): string {
        $candidates = $this->getCandidates($template);

        foreach ($candidates as [$viewsDirectory, $path]) {
            if (file_exists($path)) {
                $this->assertWithinViewsDirectory($template, $path, $viewsDirectory);

                return $path;
            }
        }

        throw TemplateNotFoundException::forTemplate(
            $template,
            array_column($candidates, 1),
        );
    }

    /**
     * @throws InvalidTemplateException
     */
    public function getSearchedPaths(
        string $template,
    ): array {
        return array_column($this->getCandidates($template), 1);
    }

    /**
     * Build the ordered list of candidate template files with the views directory each must stay inside.
     *
     * @return list<array{0: string, 1: string}> [viewsDirectory, path] pairs
     * @throws InvalidTemplateException
     */
    private function getCandidates(
        string $template,
    ): array {
        [$moduleName, $templatePath] = $this->parseTemplate($template);
        $extension = $this->viewConfig->extension();

        $candidates = [];

        $siblingCandidates = [];

        foreach ($this->moduleRepository->all() as $module) {
            $viewsDirectory = $module->path . self::VIEWS_DIRECTORY;
            $candidate = [$viewsDirectory, $viewsDirectory . $templatePath . $extension];

            if ($this->matchesModuleName($module->name, $moduleName)) {
                $candidates[] = $candidate;
                continue;
            }

            if ($this->matchesTemplatesFor($module, $moduleName)) {
                $siblingCandidates[] = $candidate;
            }
        }

        return array_merge($candidates, $siblingCandidates);
    }

    /**
     * Parse and validate a template name into module name and path.
     *
     * @return array{0: string, 1: string} [moduleName, templatePath]
     * @throws InvalidTemplateException
     */
    private function parseTemplate(
        string $template,
    ): array {
        if (str_contains($template, "\0")) {
            throw InvalidTemplateException::invalidName($template, 'it contains a NUL byte');
        }

        if (str_contains($template, '\\')) {
            throw InvalidTemplateException::invalidName($template, 'it contains a backslash');
        }

        $moduleName = '';
        $templatePath = $template;

        if (str_contains($template, '::')) {
            [$moduleName, $templatePath] = explode('::', $template, 2);

            if (preg_match(self::MODULE_NAME_PATTERN, $moduleName) !== 1) {
                throw InvalidTemplateException::invalidName(
                    $template,
                    "the module name '$moduleName' is empty or contains characters outside [A-Za-z0-9_.-]",
                );
            }
        }

        $this->validateTemplatePath($template, $templatePath);

        return [$moduleName, $templatePath];
    }

    /**
     * @throws InvalidTemplateException
     */
    private function validateTemplatePath(
        string $template,
        string $templatePath,
    ): void {
        if ($templatePath === '') {
            throw InvalidTemplateException::invalidName($template, 'the template path is empty');
        }

        if (str_starts_with($templatePath, '/')) {
            throw InvalidTemplateException::invalidName($template, 'absolute paths are not allowed');
        }

        foreach (explode('/', $templatePath) as $segment) {
            if ($segment === '') {
                throw InvalidTemplateException::invalidName($template, 'it contains an empty path segment');
            }

            if ($segment === '.' || $segment === '..') {
                throw InvalidTemplateException::invalidName(
                    $template,
                    "it contains a '$segment' path segment",
                );
            }

            if (preg_match(self::PATH_SEGMENT_PATTERN, $segment) !== 1) {
                throw InvalidTemplateException::invalidName(
                    $template,
                    "the path segment '$segment' contains characters outside [A-Za-z0-9_.-]",
                );
            }
        }
    }

    /**
     * Guard against symlinks (or any other indirection) escaping the module's views directory.
     *
     * @throws InvalidTemplateException
     */
    private function assertWithinViewsDirectory(
        string $template,
        string $path,
        string $viewsDirectory,
    ): void {
        $realPath = realpath($path);
        $realViewsDirectory = realpath($viewsDirectory);

        if (
            $realPath === false
            || $realViewsDirectory === false
            || !str_starts_with($realPath, rtrim($realViewsDirectory, '/') . '/')
        ) {
            throw InvalidTemplateException::outsideViewsDirectory(
                $template,
                $realPath === false ? $path : $realPath,
                $viewsDirectory,
            );
        }
    }

    /**
     * Check if a module declares templates_for targeting the given short module name.
     */
    private function matchesTemplatesFor(
        ModuleManifest $module,
        string $shortModuleName,
    ): bool {
        $templatesFor = $module->extra['marko']['templates_for'] ?? null;

        if (!is_string($templatesFor)) {
            return false;
        }

        return $this->matchesModuleName($templatesFor, $shortModuleName);
    }

    /**
     * Check if a full module name matches a short module name.
     * 'vendor/blog' matches 'blog', 'marko/core' matches 'core'
     * Empty shortName matches all modules (template without module prefix)
     */
    private function matchesModuleName(
        string $fullName,
        string $shortName,
    ): bool {
        if ($shortName === '') {
            return true;
        }

        if ($fullName === $shortName) {
            return true;
        }

        $parts = explode('/', $fullName);

        return end($parts) === $shortName;
    }
}
