<?php
class Test825 {
    /** @var array|null */
    protected $parsedConfig = null;

    protected function extractRequestVariables() {
        foreach ( $this->parsedConfig as $cnf => $modifiers ) {
            if ( $cnf === 'example' ) {
                $activeModifiers = $modifiers;
            }
        }
        if ( isset( $activeModifiers ) ) {
            foreach ( $activeModifiers as $cnf => $modifier ) {
                if ( !array_key_exists( $cnf, $this->parsedConfig ) ) {
                    $this->parsedConfig[$cnf] = [];
                }
                $this->parsedConfig[$cnf] = array_merge( $this->parsedConfig[$cnf], $modifier ); // Should not emit PhanTypeArraySuspiciousNullable
            }
        }
    }
}
