<?php

declare(strict_types=1);

/**
 * Safe, zero-dependency Markdown renderer for public documentation pages.
 *
 * Renders the subset of Markdown used by docs/USER_GUIDE.md:
 *   - ATX headings (#, ##, ###, ####) with GitHub-compatible anchor ids
 *   - Fenced code blocks (```)
 *   - Pipe tables (| cell | ... | with |---|---| separator rows)
 *   - Unordered lists (- item), ordered lists (1. item)
 *   - Blockquotes (> text)
 *   - Horizontal rules (---)
 *   - Paragraphs
 *   - Inline: **bold**, *italic*, `code`, [text](url)
 *
 * SECURITY MODEL — escape first, format second:
 *   1. Every line is passed through htmlspecialchars() BEFORE any Markdown
 *      formatting is applied. Raw HTML in the source document (e.g. the
 *      <script> examples the user guide deliberately contains when
 *      documenting XSS testing) is rendered as visible escaped text and
 *      can never execute.
 *   2. Link URLs are whitelisted: only http:, https:, and in-page #anchor
 *      URLs are emitted as href attributes. Anything else (javascript:,
 *      data:, other schemes, relative paths) is rendered as plain text
 *      without a link.
 *   3. No user input is ever processed — the only caller-supplied value
 *      is a server-side file path.
 *
 * GitHub slug algorithm for heading ids (must match the anchors the guide's
 * own Table of Contents links to):
 *   lowercase → remove anything that is not [a-z0-9 space -] → spaces to
 *   hyphens. "3. Getting Started — Installation" → "3-getting-started--installation".
 *
 * Public API:
 *   markdown_to_html(string $markdown): string  — render a document
 *   markdown_heading_id(string $heading): string — slug used for anchors
 */

if (!function_exists('markdown_heading_id')) {
    function markdown_heading_id(string $heading): string
    {
        $slug = mb_strtolower($heading, 'UTF-8');
        // Keep letters (incl. accented), digits, spaces, and hyphens.
        $slug = preg_replace('/[^\p{L}\p{Nd} -]/u', '', $slug) ?? '';
        $slug = str_replace(' ', '-', $slug);

        return $slug;
    }
}

if (!function_exists('markdown_safe_href')) {
    /**
     * Return a safe href for the given URL, or null when the URL is not
     * allowed. Allowed: http://, https://, and #fragment anchors.
     */
    function markdown_safe_href(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // In-page anchor.
        if ($url[0] === '#') {
            return '#' . rawurlencode(substr($url, 1));
        }

        // Absolute http(s) URLs only. parse_url scheme check rejects
        // "javascript:...", "data:...", "vbscript:...", malformed input,
        // and protocol-relative "//host" forms.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (($scheme === 'http' || $scheme === 'https')
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
            return $url;
        }

        return null;
    }
}

if (!function_exists('markdown_inline')) {
    /**
     * Inline formatting for a single already-escaped line of text:
     * `code`, **bold**, *italic*, [text](whitelisted-url).
     */
    function markdown_inline(string $escaped): string
    {
        // Inline code first — protect its contents from further formatting.
        // Pattern operates on escaped text: `...` spans are unaffected by
        // htmlspecialchars() (backticks are not special chars).
        $escaped = preg_replace_callback(
            '/`([^`]+)`/',
            static fn (array $m): string => '<code>' . $m[1] . '</code>',
            $escaped
        ) ?? $escaped;

        // Bold: **text** (before italic so ** is not half-consumed).
        $escaped = preg_replace(
            '/\*\*([^*]+)\*\*/',
            '<strong>$1</strong>',
            $escaped
        ) ?? $escaped;

        // Italic: *text* — single asterisks not adjacent to another asterisk.
        $escaped = preg_replace(
            '/(^|[^*])\*([^*\s][^*]*)\*(?!\*)/',
            '$1<em>$2</em>',
            $escaped
        ) ?? $escaped;

        // Links: [text](url) — href must pass the whitelist.
        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            /** @param array<int,string> $m */
            static function (array $m): string {
                $href = markdown_safe_href($m[2]);
                if ($href === null) {
                    // Not a permitted URL — render the text without a link.
                    return $m[1];
                }
                return '<a href="' . $href . '">' . $m[1] . '</a>';
            },
            $escaped
        ) ?? $escaped;

        return $escaped;
    }
}

if (!function_exists('markdown_table_row')) {
    /**
     * Split an already-escaped table line into cell html strings.
     * @param string $line
     * @return list<string>
     */
    function markdown_table_row(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        if ($line === '') {
            return [];
        }

        $cells = explode('|', $line);

        return array_map(static fn (string $c): string => trim($c), $cells);
    }
}

if (!function_exists('markdown_to_html')) {
    function markdown_to_html(string $markdown): string
    {
        // Normalize line endings and split.
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $count = count($lines);

        $out   = [];
        $index = 0;

        $isSeparator = static function (string $line): bool {
            return (bool) preg_match('/^\s*\|?\s*:?-{2,}[-\s:|]*\|?\s*$/', $line);
        };

        while ($index < $count) {
            $raw = $lines[$index];

            // Blank line — paragraph break.
            if (trim($raw) === '') {
                $index++;
                continue;
            }

            // Fenced code block.
            if (preg_match('/^\s*```/', $raw)) {
                $index++;
                $code = [];
                while ($index < $count && !preg_match('/^\s*```/', $lines[$index])) {
                    $code[] = htmlspecialchars($lines[$index], ENT_QUOTES, 'UTF-8');
                    $index++;
                }
                $index++; // consume closing fence
                $out[] = '<pre><code>' . implode("\n", $code) . '</code></pre>';
                continue;
            }

            // ATX heading: ## Title (allow 1-6 levels).
            if (preg_match('/^(#{1,6})\s+(.*)$/', $raw, $m)) {
                $level    = strlen($m[1]);
                $text     = trim($m[2]);
                $heading  = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                $id       = markdown_heading_id($text);
                $out[]    = sprintf(
                    '<h%d id="%s">%s</h%d>',
                    $level,
                    htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
                    markdown_inline($heading),
                    $level
                );
                $index++;
                continue;
            }

            // Horizontal rule: --- (three or more hyphens alone).
            if (preg_match('/^\s*(-{3,})\s*$/', $raw)) {
                $out[] = '<hr>';
                $index++;
                continue;
            }

            // Table: current line contains a pipe and the NEXT line is a
            // |---|---| separator.
            if (str_contains($raw, '|')
                && $index + 1 < $count
                && $isSeparator($lines[$index + 1])) {
                $headCells = markdown_table_row(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
                $index += 2; // consume header + separator

                $bodyRows = [];
                while ($index < $count
                    && str_contains($lines[$index], '|')
                    && trim($lines[$index]) !== '') {
                    $bodyRows[] = markdown_table_row(htmlspecialchars($lines[$index], ENT_QUOTES, 'UTF-8'));
                    $index++;
                }

                $table  = '<div class="ug-table-wrap"><table><thead><tr>';
                foreach ($headCells as $cell) {
                    $table .= '<th>' . markdown_inline($cell) . '</th>';
                }
                $table .= '</tr></thead><tbody>';
                foreach ($bodyRows as $row) {
                    $table .= '<tr>';
                    foreach ($row as $cell) {
                        $table .= '<td>' . markdown_inline($cell) . '</td>';
                    }
                    $table .= '</tr>';
                }
                $table .= '</tbody></table></div>';
                $out[] = $table;
                continue;
            }

            // Blockquote: consecutive lines starting with ">".
            if (preg_match('/^\s*>/', $raw)) {
                $quote = [];
                while ($index < $count && preg_match('/^\s*>/', $lines[$index])) {
                    $quote[] = htmlspecialchars(
                        trim(preg_replace('/^\s*>\s?/', '', $lines[$index]) ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $index++;
                }
                $out[] = '<blockquote><p>' . implode('<br>', array_map('markdown_inline', $quote)) . '</p></blockquote>';
                continue;
            }

            // Unordered list: consecutive "- " lines.
            if (preg_match('/^\s*-\s+/', $raw)) {
                $items = [];
                while ($index < $count && preg_match('/^\s*-\s+/', $lines[$index])) {
                    $items[] = htmlspecialchars(
                        trim(preg_replace('/^\s*-\s+/', '', $lines[$index]) ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $index++;
                }
                $out[] = '<ul>' . implode('', array_map(
                    static fn (string $i): string => '<li>' . markdown_inline($i) . '</li>',
                    $items
                )) . '</ul>';
                continue;
            }

            // Ordered list: consecutive "N. " lines.
            if (preg_match('/^\s*\d+\.\s+/', $raw)) {
                $items = [];
                while ($index < $count && preg_match('/^\s*\d+\.\s+/', $lines[$index])) {
                    $items[] = htmlspecialchars(
                        trim(preg_replace('/^\s*\d+\.\s+/', '', $lines[$index]) ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $index++;
                }
                $out[] = '<ol>' . implode('', array_map(
                    static fn (string $i): string => '<li>' . markdown_inline($i) . '</li>',
                    $items
                )) . '</ol>';
                continue;
            }

            // Paragraph: consecutive non-blank, non-structural lines.
            $para = [];
            while ($index < $count
                && trim($lines[$index]) !== ''
                && !preg_match('/^\s*(```|#{1,6}\s|>\s*|-\s|\d+\.\s)/', $lines[$index])
                && !(str_contains($lines[$index], '|') && $index + 1 < $count && $isSeparator($lines[$index + 1]))
                && !preg_match('/^\s*-{3,}\s*$/', $lines[$index])) {
                $para[] = htmlspecialchars($lines[$index], ENT_QUOTES, 'UTF-8');
                $index++;
            }
            if ($para !== []) {
                $out[] = '<p>' . implode('<br>', array_map('markdown_inline', $para)) . '</p>';
            } else {
                // Safety net: never loop forever on an unmatched line.
                $out[] = '<p>' . markdown_inline(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8')) . '</p>';
                $index++;
            }
        }

        return implode("\n", $out);
    }
}
