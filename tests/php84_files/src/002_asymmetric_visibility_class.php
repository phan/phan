<?php

class Book {
    public private(set) $price;

    public function __construct(
        public private(set) string $title,
        public protected(set) $author = 'unknown'
    ) {
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function info(): string
    {
        return $this->title . ' (' . $this->price . ') by ' . $this->author;
    }
}

$book = new Book('To Kill a Mockingbird');
$book->setPrice(12.99);
echo $book->info();

