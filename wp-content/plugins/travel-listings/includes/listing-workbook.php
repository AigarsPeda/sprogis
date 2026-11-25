<?php
defined('ABSPATH') || exit;

/** Read listing values and pictures without executing spreadsheet formulas. */
final class Spro_Listing_Workbook {
    private $zip;
    private $strings = [];
    public $date1904 = false;
    public $warnings = [];
    /** Existing trips that received included items or key dates from this upload. */
    public $details_added = 0;
    public $sheet_name = '';

    public static function fields() {
        $fields = ['post title', 'post name', 'categories', 'post status', 'featured image', 'travel price',
            'travel location', 'travel date from', 'travel date to', 'travel contact phone', 'travel contact email',
            'travel website url', 'travel price on image', 'card image', 'trip page photo', 'photo crop', 'reserve button link'];
        foreach (['lv', 'en', 'ru'] as $language) {
            foreach (['title', 'excerpt', 'description'] as $field) {
                $fields[] = "travel $field $language";
            }
            $fields[] = "price includes $language";
            $fields[] = "key dates $language";
        }
        return $fields;
    }

    public function __construct($path) {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string') || !function_exists('dom_import_simplexml')) {
            throw new RuntimeException('The server needs the PHP ZIP, DOM and SimpleXML extensions.');
        }
        $this->zip = new ZipArchive();
        if (!$path || $this->zip->open($path, ZipArchive::RDONLY) !== true || $this->zip->locateName('xl/workbook.xml') === false) {
            throw new RuntimeException('Cannot read an Excel workbook from this file. Open it in Excel or Numbers and export a fresh, unencrypted .xlsx copy. Renaming another file to .xlsx does not convert it.');
        }
        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $entry = $this->zip->statIndex($i);
            $total += $entry['size'];
            if ($i >= 4096 || $entry['size'] > 16 * MB_IN_BYTES || $total > 96 * MB_IN_BYTES) {
                throw new RuntimeException('This workbook is too large to process. Split it into smaller workbooks.');
            }
        }
    }

    public function bytes($part) {
        $data = $this->zip->getFromName($part);
        if ($data === false) {
            throw new RuntimeException('Part of this workbook is missing. Open the original spreadsheet and export a fresh .xlsx copy.');
        }
        return $data;
    }

    private function xml($part) {
        $data = $this->bytes($part);
        if (strlen($data) > 8 * MB_IN_BYTES || stripos($data, '<!DOCTYPE') !== false) {
            throw new RuntimeException('Unsupported XML in this workbook.');
        }
        $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            throw new RuntimeException('The workbook contains invalid XML.');
        }
        $root = dom_import_simplexml($xml);
        if ($root->prefix) {
            return simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET, $root->namespaceURI);
        }
        return $xml;
    }

    private function relations($part, &$hyperlinks = null) {
        $dir = dirname($part);
        $rels = $dir . '/_rels/' . basename($part) . '.rels';
        $out = [];
        if ($this->zip->locateName($rels) === false) {
            return $out;
        }
        foreach ($this->xml($rels)->Relationship as $rel) {
            if ((string) $rel->attributes()['TargetMode'] === 'External') {
                if (isset($hyperlinks) && basename((string) $rel->attributes()['Type']) === 'hyperlink') {
                    $hyperlinks[(string) $rel->attributes()['Id']] = (string) $rel->attributes()['Target'];
                }
                continue;
            }
            $target = (string) $rel->attributes()['Target'];
            if ($target === '') {
                throw new RuntimeException('The workbook has an empty relationship target.');
            }
            $pieces = explode('/', $target[0] === '/' ? substr($target, 1) : $dir . '/' . $target);
            $path = [];
            foreach ($pieces as $piece) {
                if ($piece === '..') {
                    if (!$path) {
                        throw new RuntimeException('Invalid workbook relationship.');
                    }
                    array_pop($path);
                } elseif ($piece !== '' && $piece !== '.') {
                    $path[] = $piece;
                }
            }
            $out[(string) $rel->attributes()['Id']] = implode('/', $path);
        }
        return $out;
    }

    private static function rel_id($element, $key = 'id') {
        return (string) $element->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')[$key];
    }

    private static function text($element) {
        // An empty inline-string cell can have no text element at all.
        if ($element === null) {
            return '';
        }
        return implode('', array_map('strval', $element->xpath('./*[local-name()="t"] | ./*[local-name()="r"]/*[local-name()="t"]') ?: []));
    }

    private function value($cell) {
        $type = (string) $cell->attributes()['t'];
        if ($type === 'inlineStr') {
            return self::text($cell->is);
        }
        $value = (string) $cell->v;
        if ($type === 's') {
            if (!ctype_digit($value) || !isset($this->strings[(int) $value])) {
                throw new RuntimeException('The saved cell text is missing. Re-enter the value or export the workbook again.');
            }
            return $this->strings[(int) $value];
        }
        return $value;
    }

    private function pictures($sheet, $links) {
        $pictures = [];
        foreach ($sheet->drawing as $drawing) {
            $drawing_part = $links[self::rel_id($drawing)] ?? '';
            $files = $this->relations($drawing_part);
            foreach ($this->xml($drawing_part)->xpath('/*/*[*[local-name()="from"] and *[local-name()="pic"]]') as $anchor) {
                $row = (int) $anchor->xpath('./*[local-name()="from"]/*[local-name()="row"]')[0] + 1;
                $col = (int) $anchor->xpath('./*[local-name()="from"]/*[local-name()="col"]')[0];
                $blips = $anchor->xpath('.//*[local-name()="blip"]');
                $pictures[$row][$col][] = $blips ? ($files[self::rel_id($blips[0], 'embed')] ?? '') : '';
            }
        }
        return $pictures;
    }

    /** Excel's Place in Cell uses value metadata, separate from anchored drawings. */
    private function rich_pictures() {
        $out = [];
        if ($this->zip->locateName('xl/richData/richValueRel.xml') === false) {
            return $out;
        }
        $meta = $this->xml('xl/metadata.xml');
        $values = $this->xml('xl/richData/rdrichvalue.xml');
        $structures = $this->xml('xl/richData/rdrichvaluestructure.xml');
        $rels = $this->xml('xl/richData/richValueRel.xml');
        $files = $this->relations('xl/richData/richValueRel.xml');
        $future = $meta->xpath('./*[local-name()="futureMetadata"][@name="XLRICHVALUE"]/*[local-name()="bk"]');
        foreach ($meta->valueMetadata->bk as $block) {
            $file = '';
            foreach ($block->rc as $rc) {
                $type = (int) $rc->attributes()['t'] - 1;
                $group = $future[(int) $rc->attributes()['v']] ?? null;
                if (!isset($meta->metadataTypes->metadataType[$type]) || (string) $meta->metadataTypes->metadataType[$type]->attributes()['name'] !== 'XLRICHVALUE' || !$group) {
                    continue;
                }
                $refs = $group->xpath('.//*[local-name()="rvb"]');
                if (!$refs) {
                    continue;
                }
                $value = $values->rv[(int) $refs[0]->attributes()['i']];
                $structure = $structures->s[(int) $value->attributes()['s']];
                if ((string) $structure->attributes()['t'] !== '_localImage') {
                    continue;
                }
                $index = 0;
                foreach ($structure->k as $key) {
                    if ((string) $key->attributes()['n'] === '_rvRel:LocalImageIdentifier') {
                        $rel = $rels->rel[(int) $value->v[$index]];
                        $file = $files[self::rel_id($rel)] ?? '';
                        break;
                    }
                    $index++;
                }
            }
            $out[count($out) + 1] = $file;
        }
        return $out;
    }

    private static function column_index($letters) {
        $index = 0;
        foreach (str_split((string) $letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }
        return $index;
    }

    private static function header($text) {
        $text = str_replace(["\xEF\xBB\xBF", '_'], ['', ' '], $text);
        $text = strtolower(trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text)));
        // A hint in brackets after the name, such as "Key dates EN (one per line)", is for people only.
        return trim(preg_replace('/\s*\([^()]*\)$/u', '', $text));
    }

    private function listing_sheet($book, $links) {
        $selected = null;
        foreach ($book->sheets->sheet as $entry) {
            if (in_array((string) $entry->attributes()['state'], ['hidden', 'veryHidden'], true)) {
                continue;
            }
            $sheet = $this->xml($links[self::rel_id($entry)] ?? '');
            foreach ($sheet->sheetData->row ?? [] as $row) {
                $headers = [];
                foreach ($row->c as $cell) {
                    $column = preg_replace('/[0-9]+$/', '', (string) $cell->attributes()['r']);
                    $headers[$column] = self::header($this->value($cell));
                }
                if (!array_filter($headers, 'strlen')) {
                    continue;
                }
                if (in_array('post title', $headers, true)) {
                    $name = (string) $entry->attributes()['name'];
                    if ($selected !== null) {
                        throw new RuntimeException("Both “{$this->sheet_name}” and “{$name}” contain listings. Put the trips on one visible sheet or export the sheets separately.");
                    }
                    $this->sheet_name = $name;
                    $selected = [$links[self::rel_id($entry)], $headers, (int) $row->attributes()['r']];
                }
                break;
            }
        }
        if ($selected === null) {
            throw new RuntimeException('No listing table found. Put Post title in the first nonempty header row of a visible sheet, then put one listing on each row below it.');
        }
        return $selected;
    }

    public function rows() {
        $book = $this->xml('xl/workbook.xml');
        $this->date1904 = isset($book->workbookPr) && in_array((string) $book->workbookPr->attributes()['date1904'], ['1', 'true'], true);
        $links = $this->relations('xl/workbook.xml');
        if ($this->zip->locateName('xl/sharedStrings.xml') !== false) {
            foreach ($this->xml('xl/sharedStrings.xml')->si as $string) {
                $this->strings[] = self::text($string);
            }
        }
        list($part, $headers, $header_row) = $this->listing_sheet($book, $links);
        $sheet = $this->xml($part);
        $known = self::fields();
        $columns = [];
        foreach ($headers as $column => $header) {
            if ($header === '') {
                continue;
            }
            if (!in_array($header, $known, true)) {
                foreach ($known as $expected) {
                    if (strlen($header) < 100 && !in_array($expected, $headers, true) && levenshtein($header, $expected) <= 2) {
                        throw new RuntimeException("Header $column$header_row reads “{$header}”. Did you mean “" . ucfirst($expected) . "”? Correct the header or rename the column if it is a separate note.");
                    }
                }
                $this->warnings[] = "Column $column (“{$header}”) is not a listing field. Its values are ignored.";
                continue;
            }
            if (isset($columns[$header])) {
                throw new RuntimeException("Duplicate header “{$header}” in {$columns[$header]}$header_row and $column$header_row. Keep one column for this field.");
            }
            $columns[$header] = $column;
        }
        foreach ($sheet->mergeCells->mergeCell ?? [] as $merge) {
            $ref = (string) $merge->attributes()['ref'];
            if (preg_match('/^[A-Z]+\d+:[A-Z]+(\d+)$/', $ref, $end) && (int) $end[1] >= $header_row) {
                throw new RuntimeException("Unmerge cells $ref on “{$this->sheet_name}” and put each listing’s values on its own row.");
            }
        }
        $hyperlinks = [];
        $links = $this->relations($part, $hyperlinks);
        $addresses = [];
        foreach ($sheet->hyperlinks->hyperlink ?? [] as $hyperlink) {
            $id = self::rel_id($hyperlink);
            if (isset($hyperlinks[$id])) {
                $addresses[(string) $hyperlink->attributes()['ref']] = $hyperlinks[$id];
            }
        }
        $pictures = $this->pictures($sheet, $links);
        $rich = $this->rich_pictures();
        // Pictures are accepted in the Featured image (poster), Card image and Trip page photo columns.
        $picture_cols = [];
        foreach (['featured image', 'card image', 'trip page photo'] as $field) {
            $letters = array_search($field, $headers, true);
            if ($letters !== false) {
                $picture_cols[$field] = [$letters, self::column_index($letters) - 1];
            }
        }
        foreach ($pictures as $number => $anchors) {
            if ($number > $header_row && array_diff(array_keys($anchors), array_column($picture_cols, 1))) {
                $this->warnings[] = "Row $number has a picture outside the Featured image, Card image and Trip page photo columns. It was ignored. Place the picture’s top-left corner inside the cell for its trip.";
            }
        }
        $count = 0;
        foreach ($sheet->sheetData->row as $row) {
            $n = (int) $row->attributes()['r'];
            if ($n <= $header_row) {
                continue;
            }
            $data = [];
            $errors = [];
            $found = [];
            foreach ($picture_cols as $field => [, $index]) {
                $found[$field] = $pictures[$n][$index] ?? [];
            }
            foreach ($row->c as $cell) {
                $column = preg_replace('/[0-9]+$/', '', (string) $cell->attributes()['r']);
                $key = $headers[$column] ?? '';
                $address = $column . $n;
                if ($key === '') {
                    if (isset($cell->f) || isset($cell->is) || (string) $cell->v !== '') {
                        $errors[] = "$address has data but column $column has no header. Name the column or clear the cell.";
                    }
                    continue;
                }
                if (!isset($columns[$key])) {
                    continue;
                }
                if (isset($picture_cols[$key]) && (int) $cell->attributes()['vm'] && !empty($rich[(int) $cell->attributes()['vm']])) {
                    $found[$key][] = $rich[(int) $cell->attributes()['vm']];
                    continue;
                }
                $type = (string) $cell->attributes()['t'];
                $field = ucfirst($key);
                if ($type === 'e') {
                    $errors[] = "$address ($field) contains the Excel error “{$cell->v}”. Fix the cell and save again.";
                    continue;
                }
                if (isset($cell->f) && (!isset($cell->v) || ((string) $cell->v === '' && $type !== 'str'))) {
                    $errors[] = "$address ($field) has a formula without a saved result. Recalculate and save in Excel, or paste its value.";
                    continue;
                }
                try {
                    $value = trim($this->value($cell));
                    if (isset($addresses[$address]) && in_array($key, ['travel website url', 'featured image', 'card image', 'trip page photo', 'reserve button link'], true)) {
                        $value = $addresses[$address];
                    }
                    if (in_array($key, ['travel date from', 'travel date to'], true)) {
                        $value = spro_import_date($value, $this->date1904, $type === '' || $type === 'n', $type === 'd');
                    }
                    $data[$key] = $value;
                } catch (RuntimeException $e) {
                    $errors[] = "$address ($field): " . $e->getMessage();
                }
            }
            if (!array_filter($found) && !$errors && !array_filter($data, 'strlen')) {
                continue;
            }
            foreach ($found as $field => $images) {
                if (count($images) > 1 || in_array('', $images, true)) {
                    $errors[] = "{$picture_cols[$field][0]}{$n} (" . ucfirst($field) . "): use one embedded picture or paste one direct image URL.";
                }
            }
            $count++;
            yield $n => [$data, $found['featured image'][0] ?? '', $errors, $columns, $n, $found['trip page photo'][0] ?? '', $found['card image'][0] ?? ''];
        }
        if (!$count) {
            throw new RuntimeException('The listing table contains no records. Add a Post title on the first data row and upload again.');
        }
    }
}
