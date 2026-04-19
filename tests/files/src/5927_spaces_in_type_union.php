<?php

// Test that spaces around | and & in union types are accepted inside array shapes and generics.
// See https://github.com/phan/phan/issues/5512

interface Foobar{
    /**
     * @return bool | string
     */
    public function non_array_return_annotation_with_space(): bool|string;

    /**
     * @return array{
     *   foobar: bool|string
     * }
     */
    public function array_return_annotation_without_space(): array;

    /**
     * @return array{
     *   foobar: bool | string
     * }
     */
    public function array_return_annotation_with_space(): array;

    /**
     * @return array<bool | string>
     */
    public function generic_with_space(): array;

    /**
     * @return array{key: int | null, val: bool | string}
     */
    public function array_shape_multiple_fields_with_space(): array;
}

echo Foobar::class;
