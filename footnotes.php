<?php
declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * Interactive Markdown footnotes and reference lists for Grav.
 *
 * Parsedown Extra produces the expected footnote structure
 * (`sup.footnote-ref` + `div.footnotes > ol > li#fn:N`). This plugin enhances
 * it with littlefoot, provides a [references] shortcode that moves the page's
 * rendered footnotes to the shortcode location, can keep the list visible at
 * the end of the content, and can describe the list as schema.org citations.
 */
class FootnotesPlugin extends Plugin
{
    private const PLACEHOLDER = '<!-- footnotes-references -->';

    /** Matches the single wrapper emitted by Parsedown Extra at the end of the content. */
    private const LIST_PATTERN = '~<div class="footnotes">.*</div>\s*$~s';

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onApiMarkdownEditorButtons' => ['onApiMarkdownEditorButtons', 0],
        ];
    }

    /**
     * Adds a button through Admin Next's documented editor-toolbar API.
     */
    public function onApiMarkdownEditorButtons(Event $event): void
    {
        $buttons = $event['buttons'] ?? [];
        $buttons[] = [
            'id' => 'footnote-reference',
            'plugin' => 'footnotes',
            'label' => (string) $this->grav['language']->translate('PLUGIN_FOOTNOTES.INSERT_REFERENCE'),
            'icon' => 'fa fa-asterisk',
            'insert' => [
                'content' => '[^1]',
                'mode' => 'insert-at-cursor',
            ],
            'authorize' => 'api.pages.write',
        ];
        $event['buttons'] = $buttons;
    }

    public function onPluginsInitialized(): void
    {
        // Content can be rendered by Admin Next's API as well as by the public
        // site. Register the relocation hook in both contexts; it adds no assets.
        $this->enable([
            'onPageContentProcessed' => ['onPageContentProcessed', -30],
            'onTwigInitialized' => ['onTwigInitialized', 0],
        ]);

        // littlefoot's CSS and JavaScript belong only to the public page.
        if (!$this->isAdmin()) {
            $this->enable([
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            ]);
        }
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
                return self::PLACEHOLDER;
            }
        );
    }

    /**
     * Exposes footnotes_citations(page) so a theme can merge the citations
     * into its own schema.org graph instead of using the plugin's JSON-LD.
     */
    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig\TwigFunction('footnotes_citations', function ($page = null): array {
                $page = $page ?? ($this->grav['page'] ?? null);
                return $page instanceof PageInterface ? $this->extractCitations((string) $page->content()) : [];
            })
        );
    }

    /**
     * Places the page's generated footnote list.
     *
     * Shortcode Core replaces the shortcode on the same event at priority -10.
     * This listener must therefore run later (a lower priority) to receive the
     * rendered placeholder rather than the literal [references] source.
     * The processed content is cached by Grav: configuration changes need a
     * cache clear to affect pages that were already rendered.
     */
    public function onPageContentProcessed(Event $event): void
    {
        $page = $event['page'] ?? null;
        if (!$page instanceof PageInterface) {
            return;
        }

        $content = (string) $page->content();
        $hasPlaceholder = strpos($content, self::PLACEHOLDER) !== false;
        $config = $this->mergeConfig($page, true);

        if (!$config->get('enabled', true)) {
            if ($hasPlaceholder) {
                $page->setRawContent(str_replace(self::PLACEHOLDER, '', $content));
            }
            return;
        }

        if (preg_match(self::LIST_PATTERN, $content, $match) !== 1) {
            // A shortcode without references should not render visible output.
            if ($hasPlaceholder) {
                $page->setRawContent(str_replace(self::PLACEHOLDER, '', $content));
            }
            return;
        }

        if ($hasPlaceholder) {
            $references = $this->markAsReferences($match[0], '');
            $content = preg_replace(self::LIST_PATTERN, '', $content, 1) ?? $content;
            $page->setRawContent(str_replace(self::PLACEHOLDER, $references, $content));
            return;
        }

        if ((bool) $config->get('references_auto', false)) {
            $title = trim((string) $config->get('references_title', ''));
            $references = $this->markAsReferences($match[0], $title);
            $page->setRawContent(preg_replace(self::LIST_PATTERN, $this->escapeReplacement($references), $content, 1) ?? $content);
        }
    }

    /**
     * Marks an explicitly displayed list so littlefoot leaves it readable as a
     * bibliography instead of turning it into a print-only list.
     */
    private function markAsReferences(string $list, string $title): string
    {
        $opening = '<div class="footnotes footnotes--references">';
        if ($title !== '') {
            $opening .= "\n" . '<h2 class="footnotes__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        }

        return preg_replace('~^<div class="footnotes">~', $this->escapeReplacement($opening), $list, 1) ?? $list;
    }

    private function escapeReplacement(string $value): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }

    /**
     * Builds schema.org Citation items from the rendered footnote list.
     * Footnote Markdown has no dependable bibliographic fields, therefore the
     * human-readable formatted citation is retained in the `text` field.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCitations(string $content): array
    {
        if (strpos($content, 'class="footnotes') === false) {
            return [];
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $content . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $items = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " footnotes ")]/ol/li[starts-with(@id, "fn:")]'
        );
        if ($items === false) {
            return [];
        }

        $citations = [];
        foreach ($items as $item) {
            $text = trim(preg_replace('/\s+/u', ' ', $item->textContent) ?? '');
            $text = trim(preg_replace('/\s*↩(?:\s*↩)*\s*$/u', '', $text) ?? $text);
            if ($text === '') {
                continue;
            }

            $citation = [
                '@type' => 'CreativeWork',
                'text' => $text,
            ];
            foreach ($item->getElementsByTagName('a') as $link) {
                $href = trim((string) $link->getAttribute('href'));
                if (!preg_match('~^https?://~i', $href)) {
                    continue;
                }
                $citation['url'] = $citation['url'] ?? $href;
                if (!isset($citation['identifier']) && preg_match('~^https?://(?:dx\.)?doi\.org/(10\.\d{4,9}/\S+)$~i', $href, $doi) === 1) {
                    $citation['identifier'] = [
                        '@type' => 'PropertyValue',
                        'propertyID' => 'DOI',
                        'value' => rawurldecode($doi[1]),
                    ];
                }
            }
            if (!isset($citation['identifier']) && preg_match('~\bdoi:\s*(10\.\d{4,9}/[^\s,;]+[^\s,;.])~i', $text, $doi) === 1) {
                $citation['identifier'] = [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'DOI',
                    'value' => $doi[1],
                ];
            }
            $citations[] = $citation;
        }

        return $citations;
    }

    private function createJsonLd(PageInterface $page, array $citations, string $idSuffix): string
    {
        $url = (string) $page->permalink();
        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url !== '' ? $url . $idSuffix : null,
            'url' => $url !== '' ? $url : null,
            'citation' => $citations,
        ];
        $graph = array_filter($graph, static function ($value): bool {
            return $value !== null && $value !== '';
        });
        $json = json_encode(
            $graph,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return $json === false ? '' : $json;
    }

    public function onTwigSiteVariables(): void
    {
        $page = $this->grav['page'] ?? null;
        if (!$page instanceof PageInterface) {
            return;
        }

        $c = $this->mergeConfig($page, true);
        if (!$c->get('enabled', true)) {
            return;
        }

        // Avoid loading assets on pages without footnotes.
        $content = (string) $page->content();
        if (strpos($content, 'footnote-ref') === false) {
            return;
        }

        $backlinks = (bool) $c->get('show_reference_backlinks', false);
        $options = [
            'activateOnHover'  => (bool) $c->get('hover', true),
            'hoverDelay'       => (int)  $c->get('hover_delay', 250),
            'dismissOnUnhover' => (bool) $c->get('dismiss_on_unhover', true),
            'dismissDelay'     => (int)  $c->get('dismiss_delay', 250),
            'allowMultiple'    => (bool) $c->get('allow_multiple', false),
            'showReferenceBacklinks' => $backlinks,
            'footnoteLabel'    => (string) $this->grav['language']->translate('PLUGIN_FOOTNOTES.FOOTNOTE_LABEL'),
        ];

        $assets = $this->grav['assets'];
        // Load the official stylesheet first; the local stylesheet only
        // customizes its variables and visual values.
        $assets->addCss('plugin://footnotes/assets/littlefoot.base.css', 102);
        if ((bool) $c->get('built_in_css', true)) {
            $assets->addCss('plugin://footnotes/assets/footnotes.css', 101);
        }
        if ($backlinks) {
            $assets->addInlineCss('.footnotes--references .footnote-backref { display: inline; }');
        }
        $assets->addJs('plugin://footnotes/assets/littlefoot.js', ['group' => 'bottom']);
        if ($backlinks) {
            $assets->addJs('plugin://footnotes/assets/reference-backlinks.js', ['group' => 'bottom']);
        }
        $assets->addInlineJs(
            'window.footnotesOptions = ' . json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';',
            ['group' => 'bottom']
        );
        $assets->addJs('plugin://footnotes/assets/init.js', ['group' => 'bottom']);

        // JSON-LD is added as an asset rather than written into page.content,
        // so it stays out of summaries, feeds and search indexes.
        if ((bool) $c->get('jsonld', false)) {
            $citations = $this->extractCitations($content);
            $json = $citations === [] ? '' : $this->createJsonLd($page, $citations, (string) $c->get('jsonld_id_suffix', ''));
            if ($json !== '') {
                $assets->addInlineJs($json, ['group' => 'head', 'type' => 'application/ld+json']);
            }
        }
    }
}
