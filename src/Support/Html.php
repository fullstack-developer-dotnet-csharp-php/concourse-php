<?php
declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class Html
{
    private const BLOCKED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        foreach (self::BLOCKED as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        foreach ($xpath->query('//*') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = strtolower(trim($attr->value));
                if (str_starts_with($name, 'on') || (in_array($name, ['href', 'src'], true) && str_starts_with($value, 'javascript:'))) {
                    $node->removeAttribute($attr->name);
                }
            }
            if ($node->tagName === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
            if ($node->tagName === 'img') {
                $node->setAttribute('loading', 'lazy');
            }
        }
        $root = $doc->getElementById('root');
        $out = '';
        if ($root !== null) {
            foreach ($root->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        }
        return $out;
    }
}
