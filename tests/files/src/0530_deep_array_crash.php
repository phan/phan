<?php

// Test for https://github.com/phan/phan/issues/1962
class TestClass
{
    /**
     * @return array<int,array> phan should warn about the mismatch
     */
    public function testMethod(): array
    {
        return [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'level6' => [
                                    'level7' => [
                                        'level8' => [
                                            'level9' => [
                                                'level10' => [
                                                    'level11' => [
                                                        'level12' => [
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        ],
                    ],
                ],
            ],
        ];
    }

}
