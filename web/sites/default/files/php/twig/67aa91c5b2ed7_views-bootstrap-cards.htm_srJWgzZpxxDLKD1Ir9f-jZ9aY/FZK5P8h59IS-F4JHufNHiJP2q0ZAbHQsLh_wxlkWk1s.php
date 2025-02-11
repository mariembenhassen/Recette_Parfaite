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

/* modules/contrib/views_bootstrap/templates/views-bootstrap-cards.html.twig */
class __TwigTemplate_86e2f804ad889b3a260a25463c6b3496 extends Template
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
        // line 13
        yield "
<div class=\"card-group\">
  ";
        // line 15
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["rows"] ?? null));
        foreach ($context['_seq'] as $context["key"] => $context["row"]) {
            // line 16
            yield "<div class=\"card\">
    ";
            // line 18
            yield "    ";
            if (CoreExtension::getAttribute($this->env, $this->source, $context["row"], "image", [], "any", false, false, true, 18)) {
                // line 19
                yield "        ";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["row"], "image", [], "any", false, false, true, 19), "html", null, true);
                yield "
    ";
            }
            // line 21
            yield "    ";
            if (CoreExtension::getAttribute($this->env, $this->source, $context["row"], "title", [], "any", false, false, true, 21)) {
                // line 22
                yield "      <div class=\"card-header\">";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["row"], "title", [], "any", false, false, true, 22), "html", null, true);
                yield "</div>
    ";
            }
            // line 24
            yield "    ";
            if (CoreExtension::getAttribute($this->env, $this->source, $context["row"], "content", [], "any", false, false, true, 24)) {
                // line 25
                yield "      <div class=\"card-body\">
        ";
                // line 26
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["row"], "content", [], "any", false, false, true, 26), "html", null, true);
                yield "
      </div>
    ";
            }
            // line 29
            yield "  </div>";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['key'], $context['row'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 31
        yield "</div>";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["rows"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/views_bootstrap/templates/views-bootstrap-cards.html.twig";
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
        return array (  91 => 31,  85 => 29,  79 => 26,  76 => 25,  73 => 24,  67 => 22,  64 => 21,  58 => 19,  55 => 18,  52 => 16,  48 => 15,  44 => 13,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/views_bootstrap/templates/views-bootstrap-cards.html.twig", "/var/www/html/web/modules/contrib/views_bootstrap/templates/views-bootstrap-cards.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["for" => 15, "if" => 18];
        static $filters = ["escape" => 19];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['for', 'if'],
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
