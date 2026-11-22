# Changelog

All notable changes to this project are documented in this file.

# 1.2.0

## 2026-11-23

1. [](#new)

    * Added `references_auto` and `references_title` to keep the complete reference list visible at the end of the content when the page has no `[references]` shortcode.
    * JSON-LD now covers every footnote of the page, not only a `[references]` list, and detects DOIs.
    * Added `jsonld_id_suffix` to merge the citations with the theme's `WebPage` node.
    * Added the `footnotes_citations()` Twig function for themes that build their own schema.org graph.
    * Added per-page settings in the front matter.
    * Added the `built_in_css` setting.
    * Added an Admin Next toolbar button that inserts a footnote call.
    * Added the `show_reference_backlinks` setting.
    * Added English, French and Spanish translations of the settings.

2. [](#improved)

    * JSON-LD is printed in the document head instead of `page.content`, so it no longer appears in summaries, feeds and search indexes.
    * Citations use the `CreativeWork` type; `Citation` is not a schema.org type.
    * Bundled the littlefoot MIT license.
    * The stylesheet now uses neutral `--footnotes-*` custom properties that themes can override, instead of theme-specific variable names.
    * Added a screenshot to the README.

3. [](#bugfix)

    * Popovers now use the page font instead of the browser's default serif font.

# 1.1.2

## 2026-11-16

1. [](#bugfix)

    * Kept a footnote list placed with `[references]` visible after littlefoot enhances the page.

# 1.1.1

## 2026-11-16

1. [](#bugfix)

    * Fixed `[references]` being processed before Shortcode Core replaced the shortcode, which prevented the generated reference list from being moved.

# 1.1.0

## 2026-11-16

1. [](#new)

    * Added the `[references]` shortcode to place a page's generated Markdown footnote list at a chosen location.
    * Added GPM metadata and a Shortcode Core dependency declaration.
    * Added an English README, an MIT license, and release documentation.

2. [](#improved)

    * Renamed the plugin from DAC Footnotes to the generic Footnotes package (`footnotes`).

# 1.0.0

## 2026-11-16

1. [](#new)

    * Added interactive Markdown Extra footnote previews powered by littlefoot.
