<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class InvalidTemplateException extends ViewException
{
    public static function invalidName(
        string $templateName,
        string $reason,
    ): self {
        $displayName = str_replace("\0", '\0', $templateName);

        return new self(
            message: "Invalid template name '$displayName': $reason.",
            context: 'Template names must be of the form "module::path/to/template" or "path/to/template", '
                . 'using only letters, digits, "_", "-", "." and "/" separators. '
                . 'Absolute paths, "." or ".." segments, empty segments, backslashes and NUL bytes are not allowed.',
            suggestion: 'Never build template names from unvalidated user input. '
                . 'Map user input to a fixed allow-list of template names instead.',
        );
    }

    public static function outsideViewsDirectory(
        string $templateName,
        string $resolvedPath,
        string $viewsDirectory,
    ): self {
        return new self(
            message: "Template '$templateName' resolves outside its module's views directory.",
            context: "Resolved path: $resolvedPath\nViews directory: $viewsDirectory",
            suggestion: "Templates must live inside a module's resources/views directory. "
                . 'Remove the symlink that points outside it, or move the template into the views directory.',
        );
    }
}
