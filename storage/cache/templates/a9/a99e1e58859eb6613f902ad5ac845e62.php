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

/* home.twig */
class __TwigTemplate_dc6f9ba10a912a1553b94478e18e5a34 extends Template
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
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield "<!DOCTYPE html>
<html lang=\"en\">
  <head>
    <meta charset=\"UTF-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <link href=\"./build/index.css\" rel=\"stylesheet\" type=\"text/css\" />
    <title>TV Calendar</title>
  </head>
  <body>
    <h1 class=\"text-blue-500 text-lg\">HELLO TWIG</h1>

    ";
        // line 12
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["shows"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["show"]) {
            // line 13
            yield "    <p>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, $context["show"], "_embedded", [], "any", false, false, false, 13), "show", [], "any", false, false, false, 13), "name", [], "any", false, false, false, 13), "html", null, true);
            yield "</p>
    <p>";
            // line 14
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["show"], "airdate", [], "any", false, false, false, 14), "html", null, true);
            yield "</p>

    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_key'], $context['show'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 17
        yield "  </body>
</html>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "home.twig";
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
        return array (  73 => 17,  64 => 14,  59 => 13,  55 => 12,  42 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "home.twig", "/var/www/resources/views/home.twig");
    }
}
