<?php

// PHP 8.6: doc comments for function parameters (https://wiki.php.net/rfc/parameter-doccomments)
// The comment may be before the parameter, or after it and before the comma.

function search86(
    /** Terms to search for */
    string $query,
    int $limit /** The maximum number of rows */,
): string {
    return $query . $limit;
}

class Search86
{
    public function __construct(
        /** The id of the search */
        public int $id,
        /** @var list<string> the tags to search for */
        public array $tags = [],
    ) {
    }
}

echo search86('x', 1);
echo search86(1, 'x');
$search = new Search86(1, ['a']);
echo strlen($search->tags); // the type of the promoted property comes from the doc comment of the parameter
echo count($search->tags) + $search->id;
