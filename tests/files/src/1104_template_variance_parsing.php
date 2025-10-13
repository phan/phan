<?php
/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */

/**
 * @template-covariant T
 * @template-contravariant U of object
 */
interface VarianceContract
{
    /**
     * @param U $value
     * @return T
     */
    public function transform($value);

    /**
     * @param T $value
     */
    public function invalidCovariantParameter($value): void;

    /**
     * @return U
     */
    public function invalidContravariantReturn();

    /**
     * @param array<T> $values
     */
    public function invalidCovariantArrayParameter(array $values): void;

    /**
     * @return array<U>
     */
    public function invalidContravariantArrayReturn(): array;

    /**
     * @param array{payload: T} $payload
     */
    public function invalidCovariantArrayShapeParameter(array $payload): void;
}
