<?php

class AbuseFilterHooks {

    /**
     * @param array $tags
     * @param bool $enabled
     */
    private static function fetchAllTags( array &$tags, $enabled ) {
        $x = new class() {
            function example_inner() {
                $where = [ 'afa_consequence' => 'tag', 'af_deleted' => false ];
                $where['af_enabled'] = true;
                return $where;
            }
        };
        $x->example_inner();
        function example_inner() {
            $where = [ 'afa_consequence' => 'tag', 'af_deleted' => false ];
            $where['af_enabled'] = true;
            return $where;
        }
        // Function that derives the new key value
        return function () {
            // This is a pretty awful hack.

            $where = [ 'afa_consequence' => 'tag', 'af_deleted' => false ];
            $where['af_enabled'] = true;
            return $where;
        };
    }

    /**
     * @param string[] &$tags
     */
    public static function onListDefinedTags( array &$tags ) {
        self::fetchAllTags( $tags, false );
    }

}
