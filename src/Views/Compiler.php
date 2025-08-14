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
        $pattern = '/\{\{\s*[&~!]?(?!if\b)(?!ifnot\b)(?!foreach\b)(?!endforeach\b)(?!endif\b)(?!endifnot\b)([^}]+?)\s*\}\}/';
        return preg_replace_callback($pattern, function ($matches) {
            $variable = $matches[1];
            $parts = explode('.', $variable);
            $rawFlag = strpos($matches[0], '!') > 0;
            $phpVar = '$' . ltrim(array_shift($parts), '&~!');
            foreach ($parts as $part) {
                $phpVar .= "['$part']";
            }
            return ($rawFlag ? '<?php echo ' . $phpVar . '; ?>'  : '<?php echo htmlspecialchars(' . $phpVar . ', ENT_QUOTES, \'UTF-8\'); ?>');
        }, $htmlTemplate);
    }

    private static function compileIfs($htmlTemplate)
    {
        $pattern = '/\{\{\s*if\s+(.*?)\s*\}\}/';
        $htmlTemplate = preg_replace_callback($pattern, function ($matches) {
            $variable = $matches[1];
            $parts = explode('.', $variable);
            $phpVar = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $phpVar .= "['$part']";
            }
            return '<?php if (' . $phpVar . '): ?>';
        }, $htmlTemplate);

        $pattern = '/\{\{\s*endif\s*\}\}/';
        $replacement = '<?php endif; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }

    private static function compileIfNots($htmlTemplate)
    {
        $pattern = '/\{\{\s*ifnot\s+(.*?)\s*\}\}/';
        $htmlTemplate = preg_replace_callback($pattern, function ($matches) {
            $variable = $matches[1];
            $parts = explode('.', $variable);
            $phpVar = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $phpVar .= "['$part']";
            }
            return '<?php if (!(' . $phpVar . ')): ?>';
        }, $htmlTemplate);

        $pattern = '/\{\{\s*endifnot\s*\}\}/';
        $replacement = '<?php endif; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }

    private static function compileForeaches($htmlTemplate)
    {
        $pattern = '/\{\{\s*foreach\s+(.*?)\s+as\s+(.*?)\s*\}\}/';
        $htmlTemplate = preg_replace_callback($pattern, function ($matches) {
            $variable = $matches[1];
            $parts = explode('.', $variable);
            $phpVar = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $phpVar .= "['$part']";
            }
            $item = '$' . $matches[2];
            return '<?php foreach (' . $phpVar . ' as ' . $item . '): ?>';
        }, $htmlTemplate);

        $pattern = '/\{\{\s*endforeach\s*\}\}/';
        $replacement = '<?php endforeach; ?>';
        return preg_replace($pattern, $replacement, $htmlTemplate);
    }
}
