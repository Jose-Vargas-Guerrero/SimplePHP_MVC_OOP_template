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
        return getenv('APP_ENV') === 'development';
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

        $sourceMtime = max(
            filemtime($viewsPath . $layoutFile),
            filemtime($viewsPath . $fileTemplate)
        );
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
        if(strpos($htmlContent, "{{include")){
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

    /**
     * Renderiza los bloques de Plantillas
     *
     * @param array $template_block Bloque de Plantillas a procesar
     * @param array $context        Variables en Contexto Immediato
     * @param array $parent         Variables en Contexto Padre
     * @param array $root           Variables en Contexto Raiz
     *
     * @return string
     */
    private static function _renderTemplate(
        $template_block,
        $context,
        $parent = null,
        $root = null
    ) {
        $renderedHTML = "";
        $foreachIsOpen = false;
        $ifIsOpen = false;
        $ifCondition = false;
        $ifNotIsOpen = false;
        $ifNotCondition = false;
        $withIsOpen = false;
        $innerBlock = array();
        $currentContext = "";

        if ($parent === null) {
            $parent = $context;
        }
        if ($root === null) {
            $root = $context;
        }

        foreach ($template_block as $node) {
            //buscando si es un cierre de with
            if (strpos($node, "{{endwith $currentContext}}") !== false) {
                if ($withIsOpen) {
                    $withIsOpen = false;
                    if (strpos($currentContext, "~") !== false) {
                        $withContext = $root[str_replace("~", "", $currentContext)];
                    } elseif (strpos($currentContext, "&") !== false) {
                        $withContext = $parent[str_replace("&", "", $currentContext)];
                    } else {
                        $withContext = $context[str_replace("&", "", $currentContext)];
                    }
                    $renderedHTML .=
                        self::_renderTemplate(
                            $innerBlock,
                            $withContext,
                            $context,
                            $root
                        );
                    $innerBlock = array();
                    $currentContext = "";
                    continue;
                }
            }
            //buscando si es un cierre de foreach
            if (strpos($node, "{{endfor $currentContext}}") !== false) {
                if ($foreachIsOpen) {
                    $foreachIsOpen = false;
                    if (strpos($currentContext, "~") !== false) {
                        $currentContext = str_replace("~", "", $currentContext);
                        foreach ($root[$currentContext] as $forcontext) {
                            $renderedHTML .= self::_renderTemplate(
                                $innerBlock,
                                $forcontext,
                                $context,
                                $root
                            );
                        }
                    } elseif (strpos($currentContext, "&") !== false) {
                        $currentContext = str_replace("&", "", $currentContext);
                        foreach ($parent[$currentContext] as $forcontext) {
                            $renderedHTML .= self::_renderTemplate(
                                $innerBlock,
                                $forcontext,
                                $context,
                                $root
                            );
                        }
                    } else {
                        if (isset($context[$currentContext])) {
                            foreach ($context[$currentContext] as $forcontext) {
                                $renderedHTML .= self::_renderTemplate(
                                    $innerBlock,
                                    $forcontext,
                                    $context,
                                    $root
                                );
                            }
                        }
                    }

                    $innerBlock = array();
                    $currentContext = "";
                    continue;
                }
            }

            //buscando si es un cierre de if
            if (strpos($node, "{{endifnot $currentContext}}") !== false) {
                if ($ifNotIsOpen) {
                    $ifNotIsOpen = false;
                    $renderedHTML .= ($ifNotCondition) ?
                        self::_renderTemplate(
                            $innerBlock,
                            $context,
                            $parent,
                            $root
                        ) : "";
                    $currentContext = "";
                    $innerBlock = array();
                    $ifNotCondition = false;
                    continue;
                }
            }

            if (strpos($node, "{{endif $currentContext}}") !== false) {
                if ($ifIsOpen) {
                    $ifIsOpen = false;
                    $renderedHTML .= ($ifCondition) ?
                        self::_renderTemplate(
                            $innerBlock,
                            $context,
                            $parent,
                            $root
                        ) : "";
                    $currentContext = "";
                    $innerBlock = array();
                    $ifCondition = false;
                    continue;
                }
            }

            if ($foreachIsOpen || $ifIsOpen || $ifNotIsOpen || $withIsOpen) {
                $innerBlock[] = $node;
                continue;
            }

            //buscando si es una apertura de with
            if (strpos($node, "{{with") !== false) {
                if (!$withIsOpen) {
                    $withIsOpen = true;
                    $currentContext = trim(
                        str_replace("}}", "", str_replace("{{with", "", $node))
                    );
                    continue;
                }
            }

            //buscando si es una apertura de foreach
            if (strpos($node, "{{foreach") !== false) {
                if (!$foreachIsOpen) {
                    $foreachIsOpen = true;
                    $currentContext = trim(
                        str_replace("}}", "", str_replace("{{foreach", "", $node))
                    );
                    continue;
                }
            }
            //buscando si es un if
            if (strpos($node, "{{ifnot")  !== false) {
                if (!$ifNotIsOpen) {
                    $ifNotIsOpen = true;
                    $currentContext = trim(
                        str_replace("}}", "", str_replace("{{ifnot", "", $node))
                    );
                    $ifNotCondition = false;
                    if (strpos($currentContext, "~") !== false) {
                        $tmpCurrentContext = str_replace("~", "", $currentContext);
                        if (isset($root[$tmpCurrentContext])) {
                            $ifNotCondition = ($root[$tmpCurrentContext]) == false;
                        }
                    } elseif (strpos($currentContext, "&") !== false) {
                        $tmpCurrentContext = str_replace("&", "", $currentContext);
                        if (isset($parent[$tmpCurrentContext])) {
                            $ifNotCondition = ($parent[$tmpCurrentContext]) == false;
                        }
                    } else {
                        if (isset($context[$currentContext])) {
                            $ifNotCondition = ($context[$currentContext]) == false;
                        }
                    }
                    continue;
                }
            }

            if (strpos($node, "{{if")  !== false) {
                if (!$ifIsOpen) {
                    $ifIsOpen = true;
                    $currentContext = trim(
                        str_replace("}}", "", str_replace("{{if", "", $node))
                    );
                    $ifCondition = false;
                    if (strpos($currentContext, "~") !== false) {
                        $tmpCurrentContext = str_replace("~", "", $currentContext);
                        if (isset($root[$tmpCurrentContext])) {
                            $ifCondition = ($root[$tmpCurrentContext]) && true;
                        }
                    } elseif (strpos($currentContext, "&") !== false) {
                        $tmpCurrentContext = str_replace("&", "", $currentContext);
                        if (isset($parent[$tmpCurrentContext])) {
                            $ifCondition = ($parent[$tmpCurrentContext])  && true;
                        }
                    } else {
                        if (isset($context[$currentContext])) {
                            $ifCondition = ($context[$currentContext]) && true;
                        }
                    }
                    continue;
                }
            }

            //remplazando las variables del nodo
            $nodeReplace = preg_split(
                "/(\{\{[&,~]?\w*\}\})/",
                $node,
                -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
            );
            foreach ($nodeReplace as $item) {
                if (strpos($item, "{{")  !== false) {
                    $index = trim(
                        str_replace("}}", "", str_replace("{{", "", $item))
                    );
                    if (strpos($index, "~") !== false) {
                        $index = str_replace("~", "", $index);
                        if ($index === "this" && !(is_array($root))) {
                            $item = $root;
                        } else {
                            $item = isset($root[$index]) ? $root[$index] : "";
                        }
                    } elseif (strpos($index, "&") !== false) {
                        $index = str_replace("&", "", $index);
                        if ($index === "this" && !(is_array($parent))) {
                            $item = $parent;
                        } else {
                            $item = isset($parent[$index]) ? $parent[$index] : "";
                        }
                    } else {
                        if ($index === "this" && !(is_array($context))) {
                            $item = $context;
                        } else {
                            $item = isset($context[$index]) ? $context[$index] : "";
                        }
                    }
                }
                $renderedHTML .= $item;
            }
        }
        return $renderedHTML;
    }
    /**
     * Obtiene los Macro Bloques de Plantillas
     *
     * @param string $htmlTemplate Plantilla a Analizar
     *
     * @return array
     */
    private static function _parseTemplate($htmlTemplate)
    {
        $regexp_array = array(
          'foreach'      => '(\{\{foreach [~&]?\w*\}\})',
          'endfor'       => '(\{\{endfor [~&]?\w*\}\})',
          'if'           => '(\{\{if [~&]?\w*\}\})',
          'if_not'       => '(\{\{ifnot [~&]?\w*\}\})',
          'if_close'     => '(\{\{endif [~&]?\w*\}\})',
          'ifnot_close'  => '(\{\{endifnot [~&]?\w*\}\})',
          'with'         => '(\{\{with [~&]?\w*\}\})',
          'with_close'   => '(\{\{endwith [~&]?\w*\}\})'
        );

        $tag_regexp = "/" . join("|", $regexp_array) . "/";

        //split the code with the tags regexp
        $template_code = preg_split(
            $tag_regexp,
            $htmlTemplate,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        return $template_code;
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
        foreach($template_code as $block) {
            if (strpos($block, "include")) {
                $filePath = trim(
                        str_replace("}}", "", str_replace("{{include", "", $block))
                ). ".view.tpl";;
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
                $pageEnd = strpos($node, "&")?:strlen($node);
                $pageValueLength = $pageEnd - $pageStart;
                $page = substr($node, $pageStart, $pageValueLength);
                $query = substr($node, $pageEnd + 1);

                $url = $basedir . "/" . str_replace(array("_",".","-"), "/", $page);
                $url .= strlen($query)?"/?".$query:"/";
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
    private function __construct()
    {

    }
}
