<?php

declare(strict_types=1);

namespace VitrineExpress;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Nettoie le HTML produit par l'éditeur (Quill) avec une liste blanche stricte (décision D5).
 * Les balises inconnues sont retirées en gardant leur texte ; les balises dangereuses sont retirées avec leur contenu.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'span'];

    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math',
        'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'img', 'video', 'audio',
    ];

    private const CLASS_PATTERN = '/^ql-(align-(center|right|justify)|size-(small|large|huge)|indent-[1-8])$/';

    public const MAX_LENGTH = 20000;

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="vx-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($doc))->query('//div[@id="vx-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }
        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /** Vrai si le HTML ne contient aucun texte visible. */
    public static function isBlank(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    // Section CDATA : la remplacer par du texte simple.
                    $node->replaceChild($node->ownerDocument->createTextNode($child->textContent), $child);
                }
                continue;
            }
            if (!$child instanceof DOMElement) {
                $node->removeChild($child); // commentaires, instructions, etc.
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROPPED_TAGS, true) || preg_match('/(^|\s)ql-ui(\s|$)/', $child->getAttribute('class'))) {
                $node->removeChild($child);
                continue;
            }

            self::cleanChildren($child);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;
            $keep = null;

            if ($name === 'class') {
                $classes = array_filter(
                    preg_split('/\s+/', trim($value)) ?: [],
                    static fn (string $class): bool => (bool) preg_match(self::CLASS_PATTERN, $class)
                );
                $keep = $classes ? implode(' ', $classes) : null;
            } elseif ($name === 'data-list' && $tag === 'li' && in_array($value, ['bullet', 'ordered'], true)) {
                $keep = $value;
            }

            $element->removeAttribute($attribute->name);
            if ($keep !== null) {
                $element->setAttribute($name, $keep);
            }
        }
    }
}
