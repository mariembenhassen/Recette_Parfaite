<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* core/modules/image/templates/image-scale-and-crop-summary.html.twig */
class __TwigTemplate_8ab108e5675a62e589ebaf905a4decf5 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 20
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "width", [], "any", false, false, true, 20) && CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "height", [], "any", false, false, true, 20))) {
            // line 21
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "width", [], "any", false, false, true, 21), "html", null, true);
            yield "×";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "height", [], "any", false, false, true, 21), "html", null, true);
        } else {
            // line 23
            if (CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "width", [], "any", false, false, true, 23)) {
                // line 24
                yield "    ";
                yield t("width @data.width", array("@data.width" => CoreExtension::getAttribute($this->env, $this->source,                 // line 25
($context["data"] ?? null), "width", [], "any", false, false, true, 25), ));
                // line 27
                yield "  ";
            } elseif (CoreExtension::getAttribute($this->env, $this->source, ($context["data"] ?? null), "height", [], "any", false, false, true, 27)) {
                // line 28
                yield "    ";
                yield t("height @data.height", array("@data.height" => CoreExtension::getAttribute($this->env, $this->source,                 // line 29
($context["data"] ?? null), "height", [], "any", false, false, true, 29), ));
                // line 31
                yield "  ";
            }
        }
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["data"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/modules/image/templates/image-scale-and-crop-summary.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  64 => 31,  62 => 29,  60 => 28,  57 => 27,  55 => 25,  53 => 24,  51 => 23,  46 => 21,  44 => 20,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/modules/image/templates/image-scale-and-crop-summary.html.twig", "/var/www/html/web/core/modules/image/templates/image-scale-and-crop-summary.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 20, "trans" => 24];
        static $filters = ["escape" => 21];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'trans'],
                ['escape'],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
