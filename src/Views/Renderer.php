<?php

/**
 * PHP Version 7.2
 *
 * @category Utility
 * @package  Views
 * @author   Orlando J Betancourth <orlando.betancourth@gmail.com>
 * @license  MIT http://
 * @version  CVS:1.0.0
 * @link     http://
 */

namespace Views;

/**
 * Renderer View Utility
 *
 * @category Utility
 * @package  Views
 * @author   Orlando J Betancourth <orlando.betancourth@gmail.com>
 * @license  MIT http://
 * @link     http://
 */
class Renderer
{
    private static function isDevMode()
    {
        return \Utilities\Context::getContextByKey("DEVELOPMENT") === "1";
    }
    /**
     * Renderiza el documento html con los datos enviados
     *
     * @param string  $vista      Archivo de la Vista
     * @param array   $datos      Datos a usar en la Vista
     * @param string  $layoutFile Master Page
     * @param boolean $render     Determina si renderiza o Devuelve la Cadena
     *
     * @return void|string
     */
    public static function render(
        $vista,
        $datos,
        $layoutFile = "layout.view.tpl",
        $render = true
    ) {
        if (!is_array($datos)) {
            http_response_code(404);
            die("Error de renderizador: datos no es un arreglo");
        }

        //union de los dos arreglos
        $global_context = \Utilities\Context::getContext();
        if (is_array($global_context)) {
            $datos = array_merge($global_context, $datos);
        }
        //union de variables de sessión
        $datos = array_merge($_SESSION, $datos);
        if (isset($datos["layoutFile"]) && $layoutFile === "layout.view.tpl") {
            $layoutFile = $datos["layoutFile"];
        }
        if (strpos($layoutFile, ".view.tpl") === false) {
            $layoutFile .= ".view.tpl";
        }

        $viewsPath = "src/Views/templates/";
        $fileTemplate = $vista . ".view.tpl";

        $cachePath = "src/Views/cache/";
        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        $cacheFile = $cachePath . md5($layoutFile . $fileTemplate) . ".php";

        $sourceMtime = 0;
        if (file_exists($viewsPath . $layoutFile)) {
            $sourceMtime = max($sourceMtime, filemtime($viewsPath . $layoutFile));
        }
        if (file_exists($viewsPath . $fileTemplate)) {
            $sourceMtime = max($sourceMtime, filemtime($viewsPath . $fileTemplate));
        }
        $cacheMtime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;

        $partialsMtime = 0;
        if (file_exists($cacheFile . ".meta")) {
            $partials = unserialize(file_get_contents($cacheFile . ".meta"));
            foreach ($partials as $partial) {
                $partialsMtime = max($partialsMtime, filemtime($partial));
            }
        }

        if (self::isDevMode() || $sourceMtime > $cacheMtime || $partialsMtime > $cacheMtime) {
            list($compiledTemplate, $partials) = self::_compileTemplate(
                $viewsPath,
                $layoutFile,
                $fileTemplate
            );
            file_put_contents($cacheFile, $compiledTemplate);
            file_put_contents($cacheFile . ".meta", serialize($partials));
        }

        ob_start();
        extract($datos);
        include $cacheFile;
        $htmlResult = ob_get_clean();

        if ($render) {
            if ($datos["USE_URLREWRITE"] == "1") {
                echo self::rewriteUrl($htmlResult);
            } else {
                echo $htmlResult;
            }
        } else {
            return $htmlResult;
        }
    }

    private static function _compileTemplate(
        $viewsPath,
        $layoutFile,
        $fileTemplate
    ) {
        if (!file_exists($viewsPath . $layoutFile)) {
            throw new \Exception("Layout file not found: " . $viewsPath . $layoutFile);
        }
        $htmlContent = file_get_contents($viewsPath . $layoutFile);

        if (!file_exists($viewsPath . $fileTemplate)) {
            throw new \Exception("View file not found: " . $viewsPath . $fileTemplate);
        }
        $tmphtml = file_get_contents($viewsPath . $fileTemplate);
        $htmlContent = str_replace(
            "{{{page_content}}}",
            $tmphtml,
            $htmlContent
        );
        //Cargar Otras plantillas
        $partials = [];
        if (strpos($htmlContent, "{{include")) {
            list($htmlContent, $partials) = self::loadPartials($htmlContent);
        }
        //Limpiar Saltos de Pagina
        if (strpos($htmlContent, "<pre>")) {
        } else {
            $htmlContent = str_replace("\n", "", $htmlContent);
            $htmlContent = str_replace("\r", "", $htmlContent);
            $htmlContent = str_replace("\t", "", $htmlContent);
            $htmlContent = str_replace("  ", "", $htmlContent);
        }
        $htmlResult = Compiler::compile($htmlContent);
        return [$htmlResult, $partials];
    }


    private static function loadPartials($htmlTemplate)
    {
        $regexp_array = array(
            'includes '      => '(\{\{include [\w\/]*\}\})',
        );

        $tag_regexp = "/" . join("|", $regexp_array) . "/";

        //split the code with the tags regexp
        $template_code = preg_split(
            $tag_regexp,
            $htmlTemplate,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        $htmlBuffer = "";
        $partials = [];
        foreach ($template_code as $block) {
            if (strpos($block, "include")) {
                $filePath = trim(
                    str_replace("}}", "", str_replace("{{include", "", $block))
                ) . ".view.tpl";;
                $viewsPath = "src/Views/templates/";
                if (file_exists($viewsPath . $filePath)) {
                    $htmlContent = file_get_contents($viewsPath . $filePath);
                    $htmlBuffer .= $htmlContent;
                    $partials[] = $viewsPath . $filePath;
                } else {
                    $htmlBuffer .= $block;
                }
            } else {
                $htmlBuffer .= $block;
            }
        }
        return [$htmlBuffer, $partials];
    }

    public static function rewriteUrl($htmlTemplate)
    {
        $regexp_array = array(
            'page '      => '(index.php\??[\w=&]*)',
        );

        $tag_regexp = "/" . join("|", $regexp_array) . "/";

        //split the code with the tags regexp
        $template_code = preg_split(
            $tag_regexp,
            $htmlTemplate,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        $htmlBuffer = "";
        $basedir = \Utilities\Context::getContextByKey("BASE_DIR");
        foreach ($template_code as $node) {
            if (strpos($node, "index.php?page=")  !== false) {
                $pageStart = strpos($node, "=") + 1;
                $pageEnd = strpos($node, "&") ?: strlen($node);
                $pageValueLength = $pageEnd - $pageStart;
                $page = substr($node, $pageStart, $pageValueLength);
                $query = substr($node, $pageEnd + 1);

                $url = $basedir . "/" . str_replace(array("_", ".", "-"), "/", $page);
                $url .= strlen($query) ? "/?" . $query : "/";
                $htmlBuffer .= $url;
            } else {
                if ($node == "index.php") {
                    $htmlBuffer .=  $basedir . "/index";
                } else {
                    $htmlBuffer .= $node;
                }
            }
        }
        return $htmlBuffer;
    }
    /**
     * Constructor privado evita instancia de esta clase
     */
    private function __construct() {}
}
