<?php

namespace Views;

class Compiler
{
    public static function compile($htmlTemplate)
    {
        // Note: The 'with' statement is not supported by this compiler.
        // It would require a more complex, stateful parser.
        $htmlTemplate = self::compileVariables($htmlTemplate);
        $htmlTemplate = self::compileIfs($htmlTemplate);
        $htmlTemplate = self::compileIfNots($htmlTemplate);
        $htmlTemplate = self::compileForeaches($htmlTemplate);
        return $htmlTemplate;
    }

    private static function compileVariables($htmlTemplate)
    {
        $pattern = '/\{\{\s*[&~]?(.*?)\s*\}\}/';
        $replacement = '<?php echo htmlspecialchars($$1, ENT_QUOTES, \'UTF-8\'); ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }

    private static function compileIfs($htmlTemplate)
    {
        $pattern = '/\{\{\s*if\s+(.*?)\s*\}\}/';
        $replacement = '<?php if ($$1): ?>';
        $htmlTemplate = preg_replace($pattern, $replacement, $htmlTemplate);

        $pattern = '/\{\{\s*endif\s*\}\}/';
        $replacement = '<?php endif; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }

    private static function compileIfNots($htmlTemplate)
    {
        $pattern = '/\{\{\s*ifnot\s+(.*?)\s*\}\}/';
        $replacement = '<?php if (!($$1)): ?>';
        $htmlTemplate = preg_replace($pattern, $replacement, $htmlTemplate);

        $pattern = '/\{\{\s*endifnot\s*\}\}/';
        $replacement = '<?php endif; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }

    private static function compileForeaches($htmlTemplate)
    {
        $pattern = '/\{\{\s*foreach\s+(.*?)\s+as\s+(.*?)\s*\}\}/';
        $replacement = '<?php foreach ($$1 as $$2): ?>';
        $htmlTemplate = preg_replace($pattern, $replacement, $htmlTemplate);

        $pattern = '/\{\{\s*endforeach\s*\}\}/';
        $replacement = '<?php endforeach; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }
}
