<?php
declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * Interactive Markdown footnotes and reference lists for Grav.
 *
 * Parsedown Extra produces the expected footnote structure
 * (`sup.footnote-ref` + `div.footnotes > ol > li#fn:N`). This plugin enhances
 * it with littlefoot and provides a [references] shortcode that moves the
 * page's rendered footnotes to the shortcode location.
 */
class FootnotesPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPageContentProcessed' => ['onPageContentProcessed', -30],
            'onTwigSiteVariables'    => ['onTwigSiteVariables', 0],
        ]);
    }

    /**
     * Registers [references] with the Shortcode Core plugin.
     *
     * The placeholder is replaced after Markdown has rendered the footnotes,
     * so references retain all links and inline Markdown created by Parsedown.
     */
    public function onShortcodeHandlers(): void
    {
        if (!isset($this->grav['shortcode'])) {
            return;
        }

        $this->grav['shortcode']->getHandlers()->add(
            'references',
            static function (ShortcodeInterface $shortcode): string {
                return '<!-- footnotes-references -->';
            }
        );
    }

    /**
     * Moves the page's generated footnote list to the [references] placeholder.
     *
     * Shortcode Core replaces the shortcode on the same event at priority -10.
     * This listener must therefore run later (a lower priority) to receive the
     * rendered placeholder rather than the literal [references] source.
     */
    public function onPageContentProcessed(Event $event): void
    {
        $page = $event['page'] ?? null;
        if ($page === null) {
            return;
        }

        $content = (string) $page->content();
        $placeholder = '<!-- footnotes-references -->';
        if (strpos($content, $placeholder) === false) {
            return;
        }

        // Parsedown Extra emits one wrapping div; the content within it can
        // contain nested div elements, so match through the final footnotes div.
        $pattern = '~<div class="footnotes">.*</div>\s*$~s';
        if (preg_match($pattern, $content, $match) !== 1) {
            // A shortcode without references should not render visible output.
            $page->setRawContent(str_replace($placeholder, '', $content));
            return;
        }

        $references = $match[0];
        $content = preg_replace($pattern, '', $content, 1) ?? $content;
        $page->setRawContent(str_replace($placeholder, $references, $content));
    }

    public function onTwigSiteVariables(): void
    {
        $page = $this->grav['page'] ?? null;
        if ($page === null) {
            return;
        }

        // Rien à charger sur les pages sans note : la grande majorité du site.
        if (strpos((string) $page->content(), 'footnote-ref') === false) {
            return;
        }

        $c = $this->config->get('plugins.footnotes');
        $options = [
            'activateOnHover'  => (bool) ($c['hover'] ?? true),
            'hoverDelay'       => (int)  ($c['hover_delay'] ?? 250),
            'dismissOnUnhover' => (bool) ($c['dismiss_on_unhover'] ?? true),
            'dismissDelay'     => (int)  ($c['dismiss_delay'] ?? 250),
            'allowMultiple'    => (bool) ($c['allow_multiple'] ?? false),
        ];

        $assets = $this->grav['assets'];
        // Feuille officielle d'abord : elle pose les variables et masque
        // le marqueur d'origine ; la nôtre ne fait que redéfinir les valeurs.
        $assets->addCss('plugin://footnotes/assets/littlefoot.base.css', 102);
        $assets->addCss('plugin://footnotes/assets/footnotes.css', 101);
        $assets->addJs('plugin://footnotes/assets/littlefoot.js', ['group' => 'bottom']);
        $assets->addInlineJs(
            'window.footnotesOptions = ' . json_encode($options, JSON_UNESCAPED_UNICODE) . ';',
            ['group' => 'bottom']
        );
        $assets->addJs('plugin://footnotes/assets/init.js', ['group' => 'bottom']);
    }
}
