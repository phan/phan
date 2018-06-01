<?php

interface myInterface {
}

/**
 * @phan-suppress PhanTypeInvalidThrowsIsInterface
 * @throws myInterface
 * @throws myInterface2
 */
function example() {

}

