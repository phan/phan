<?php declare(strict_types=1);
namespace Phan;

/**
 * An issue emitted during the course of analysis
 */
class Issue {

    // Issue::CLASS_UNDEFINED
    const EmptyFile                 = 'EmptyFile';
    const ParentlessClass           = 'ParentlessClass';
    const TraitParentReference      = 'TraitParentReference';
    const UndeclaredClassCatch      = 'UndeclaredClassCatch';
    const UndeclaredClassConstant   = 'UndeclaredClassConstant';
    const UndeclaredClassInherit    = 'UndeclaredClassInherit';
    const UndeclaredClassInstanceof = 'UndeclaredClassInstanceof';
    const UndeclaredClassMethod     = 'UndeclaredClassMethod';
    const UndeclaredClassParent     = 'UndeclaredClassParent';
    const UndeclaredClassReference  = 'UndeclaredClassReference';
    const UndeclaredConstant        = 'UndeclaredConstant';
    const UndeclaredFunction        = 'UndeclaredFunction';
    const UndeclaredInterface       = 'UndeclaredInterface';
    const UndeclaredMethod          = 'UndeclaredMethod';
    const UndeclaredParentClass     = 'UndeclaredParentClass';
    const UndeclaredProperty        = 'UndeclaredProperty';
    const UndeclaredStaticMethod    = 'UndeclaredStaticMethod';
    const UndeclaredStaticProperty  = 'UndeclaredStaticProperty';
    const UndeclaredTrait           = 'UndeclaredTrait';
    const UndeclaredTypeParameter   = 'UndeclaredTypeParameter';
    const UndeclaredTypeProperty    = 'UndeclaredTypeProperty';
    const UndeclaredVariable        = 'UndeclaredVariable';

    // Issue::CLASS_TYPE
    const NonClassMethodCall        = 'NonClassMethodCall';
    const TypeArrayOperator         = 'TypeArrayOperator';
    const TypeArraySuspicious       = 'TypeArraySuspicious';
    const TypeComparisonFromArray   = 'TypeComparisonFromArray';
    const TypeComparisonToArray     = 'TypeComparisonToArray';
    const TypeConversionFromArray   = 'TypeConversionFromArray';
    const TypeInstantiateAbstract   = 'TypeInstantiateAbstract';
    const TypeInstantiateInterface  = 'TypeInstantiateInterface';
    const TypeInvalidLeftOperand    = 'TypeInvalidLeftOperand';
    const TypeInvalidRightOperand   = 'TypeInvalidRightOperand';
    const TypeMismatchArgument      = 'TypeMismatchArgument';
    const TypeMismatchArgumentInternal = 'TypeMismatchArgumentInternal';
    const TypeMismatchDefault       = 'TypeMismatchDefault';
    const TypeMismatchForeach       = 'TypeMismatchForeach';
    const TypeMismatchProperty      = 'TypeMismatchProperty';
    const TypeMismatchReturn        = 'TypeMismatchReturn';
    const TypeMissingReturn         = 'TypeMissingReturn';
    const TypeNonVarPassByRef       = 'TypeNonVarPassByRef';
    const TypeParentConstructorCalled = 'TypeParentConstructorCalled';

    // Issue::CLASS_ANALYSIS
    const Unanalyzable              = 'Unanalyzable';

    // Issue::CLASS_VARIABLE
    const VariableUseClause         = 'VariableUseClause';

    // Issue::CLASS_STATIC
    const StaticCallToNonStatic     = 'StaticCallToNonStatic';
    const NonStaticSelf             = 'NonStaticSelf';

    // Issue::CLASS_CONTEXT
    const ContextNotObject          = 'ContextNotObject';

    // Issue::CLASS_DEPRECATED
    const DeprecatedFunction        = 'DeprecatedFunction';

    // Issue::CLASS_PARAMETER
    const ParamReqAfterOpt          = 'ParamReqAfterOpt';
    const ParamSpecial1             = 'ParamSpecial1';
    const ParamSpecial2             = 'ParamSpecial2';
    const ParamSpecial3             = 'ParamSpecial3';
    const ParamSpecial4             = 'ParamSpecial4';
    const ParamTypeMismatch         = 'ParamTypeMismatch';
    const ParamTooFew               = 'ParamTooFew';
    const ParamTooFewInternal       = 'ParamTooFewInternal';
    const ParamTooMany              = 'ParamTooMany';
    const ParamTooManyInternal      = 'ParamTooManyInternal';

    // Issue::CLASS_NOOP
    const NoopProperty              = 'NoopProperty';
    const NoopArray                 = 'NoopArray';
    const NoopConstant              = 'NoopConstant';
    const NoopClosure               = 'NoopClosure';
    const NoopVariable              = 'NoopVariable';
    const NoopZeroReferences        = 'NoopZeroReferences';

    // Issue::CLASS_REDEFINE
    const RedefineClass             = 'RedefineClass';
    const RedefineClassInternal     = 'RedefineClassInternal';
    const RedefineFunction          = 'RedefineFunction';
    const RedefineFunctionInternal  = 'RedefineFunctionInternal';

    // Issue::CLASS_ACCESS
    const AccessPropertyProtected   = 'AccessPropertyProtected';
    const AccessPropertyPrivate     = 'AccessPropertyPrivate';


    // Issue::CLASS_COMPATIBLE
    const CompatiblePHP7            = 'CompatiblePHP7';
    const CompatibleExpressionPHP7  = 'CompatibleExpressionPHP7';

	const CLASS_REDEFINE          = 1<<0;
	const CLASS_UNDEFINED         = 1<<1;
	const CLASS_TYPE              = 1<<2;
	const CLASS_PARAMETER         = 1<<3;
	const CLASS_VARIABLE          = 1<<4;
	const CLASS_NOOP              = 1<<5;
	const CLASS_OPTIONAL_REQUIRED = 1<<6;
	const CLASS_STATIC            = 1<<6;
	const CLASS_AVAILABLE         = 1<<8;
	const CLASS_TAINT             = 1<<9;
	const CLASS_COMPATIBLE        = 1<<10;
	const CLASS_ACCESS            = 1<<11;
	const CLASS_DEPRECATED        = 1<<12;
	const CLASS_ANALYSIS          = 1<<13;
	const CLASS_CONTEXT           = 1<<14;
	const CLASS_FATAL             = -1;

    const CLASS_NAME = [
        self::CLASS_REDEFINE          => 'RedefineError',
        self::CLASS_UNDEFINED         => 'UndefError',
        self::CLASS_TYPE              => 'TypeError',
        self::CLASS_PARAMETER         => 'ParamError',
        self::CLASS_VARIABLE          => 'VarError',
        self::CLASS_NOOP              => 'NOOPError',
        self::CLASS_OPTIONAL_REQUIRED => 'ReqAfterOptError',
        self::CLASS_STATIC            => 'StaticCallError',
        self::CLASS_AVAILABLE         => 'AvailError',
        self::CLASS_TAINT             => 'TaintError',
        self::CLASS_COMPATIBLE        => 'CompatError',
        self::CLASS_ACCESS            => 'AccessError',
        self::CLASS_DEPRECATED        => 'DeprecatedError',
        self::CLASS_ANALYSIS          => 'Analysis',
        self::CLASS_CONTEXT           => 'Context',
    ];

    const SEVERITY_LOW      = 1<<0;
    const SEVERITY_NORMAL   = 1<<1;
    const SEVERITY_CRITICAL = 1<<2;

    /** @var string */
    private $type;

    /** @var int */
    private $class;

    /** @var int */
    private $severity;

    /** @var string */
    private $template;


    /**
     * @param string $type
     * @param int $class
     * @param int $severity
     * @param string $template
     */
    public function __construct(
        string $type,
        int $class,
        int $severity,
        string $template
    ) {
        $this->type = $type;
        $this->class = $class;
        $this->severity = $severity;
        $this->template = $template;
    }

    /**
     * @return Issue[]
     */
    private static function errorMap() {
        static $error_map;

        if (!empty($error_map)) {
            return $error_map;
        }

        $error_list = [

            // Issue::CLASS_UNDEFINED
            new Issue(self::EmptyFile, self::CLASS_UNDEFINED, self::SEVERITY_LOW,
                "Empty file %s"
            ),
            new Issue(self::ParentlessClass, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to parent of class %s that does not extend anything"
            ),
            new Issue(self::UndeclaredClassParent, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to undeclared parent class from %s"
            ), // TODO: merge with Issue::ParentlessClass ?
            new Issue(self::UndeclaredParentClass, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to undeclared parent class %s"
            ), // TODO: merge with Issue::ParentlessClass ?
            new Issue(self::UndeclaredClassInherit, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class extends undeclared class %s"
            ), // TODO: rename UndeclaredClassExtend
            new Issue(self::UndeclaredInterface, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class implements undeclared interface %s"
            ),
            new Issue(self::UndeclaredTrait, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class uses undeclared trait %s"
            ),
            new Issue(self::UndeclaredClassCatch, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Catching undeclared class %s"
            ),
            new Issue(self::UndeclaredClassConstant, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to constant %s from undeclared class %s"
            ),
            new Issue(self::UndeclaredClassInstanceof, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Checking instanceof against undeclared class %s"
            ),
            new Issue(self::UndeclaredClassMethod, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Call to method %s from undeclared class %s"
            ),
            new Issue(self::UndeclaredClassReference, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared class %s"
            ),
            new Issue(self::UndeclaredConstant, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared constant %s"
            ),
            new Issue(self::UndeclaredFunction, self::CLASS_UNDEFINED, self::SEVERITY_CRITICAL,
                "Call to undeclared function %s"
            ),
            new Issue(self::UndeclaredMethod, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Call to undeclared method %s"
            ),
            new Issue(self::UndeclaredStaticMethod, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Static call to undeclared method %s"
            ),
            new Issue(self::UndeclaredProperty, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared property %s"
            ),
            new Issue(self::UndeclaredStaticProperty, self::CLASS_UNDEFINED, self::SEVERITY_NORMAL,
                "Static property '%s' on %s is undeclared"
            ),
            new Issue(self::TraitParentReference, self::CLASS_UNDEFINED, self::SEVERITY_LOW,
                "Reference to parent from trait %s"
            ),
            new Issue(self::UndeclaredVariable, self::CLASS_UNDEFINED, self::SEVERITY_LOW,
                "Variable \$%s is undeclared"
            ),

            // Issue::CLASS_ANALYSIS
            new Issue(self::Unanalyzable, self::CLASS_UNDEFINED, self::SEVERITY_LOW,
                "Expression is unanalyzable or feature is unimplemented. Please create an issue at https://github.com/etsy/phan/issues/new."
            ),

            // Issue::CLASS_TYPE
            new Issue(self::TypeMismatchProperty, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Assigning %s to property but %s is %s"
            ),
            new Issue(self::TypeMismatchDefault, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Default value for %s \$%s can't be %s"
            ),
            new Issue(self::TypeMismatchArgument, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s defined at %s:%d"
            ),
            new Issue(self::TypeMismatchArgumentInternal, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s"
            ),
            new Issue(self::TypeMismatchReturn, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Returning type %s but %s() is declared to return %s"
            ),
            new Issue(self::TypeMissingReturn, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Method %s is declared to return %s but has no return value"
            ),
            new Issue(self::TypeMismatchForeach, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "%s passed to foreach instead of array"
            ),
            new Issue(self::TypeArrayOperator, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Invalid array operator"
            ),
            new Issue(self::TypeArraySuspicious, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Suspicious array access to %s"
            ),
            new Issue(self::TypeComparisonToArray, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "%s to array comparison"
            ),
            new Issue(self::TypeComparisonFromArray, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "array to %s comparison"
            ),
            new Issue(self::TypeConversionFromArray, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "array to %s conversion"
            ),
            new Issue(self::TypeInstantiateAbstract, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Instantiation of abstract class %s"
            ),
            new Issue(self::TypeInstantiateInterface, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Instantiation of interface %s"
            ),
            new Issue(self::TypeInvalidRightOperand, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Invalid operator: left operand is array and right is not"
            ),
            new Issue(self::TypeInvalidLeftOperand, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Invalid operator: right operand is array and left is not"
            ),
            new Issue(self::TypeParentConstructorCalled, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Must call parent::__construct() from %s which extends %s"
            ),
            new Issue(self::TypeNonVarPassByRef, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Only variables can be passed by reference at argument %d of %s()"
            ),
            new Issue(self::UndeclaredTypeParameter, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Parameter of undeclared type %s"
            ),
            new Issue(self::UndeclaredTypeProperty, self::CLASS_TYPE, self::SEVERITY_NORMAL,
                "Property of undeclared type %s"
            ),
            new Issue(self::NonClassMethodCall, self::CLASS_TYPE, self::SEVERITY_CRITICAL,
                "Call to method on non-class type %s"
            ),

            // Issue::CLASS_VARIABLE
            new Issue(self::VariableUseClause, self::CLASS_VARIABLE, self::SEVERITY_CRITICAL,
                "Non-variables not allowed within use clause"
            ),

            // Issue::CLASS_STATIC
            new Issue(self::StaticCallToNonStatic, self::CLASS_STATIC, self::SEVERITY_NORMAL,
                "Static call to non-static method %s defined at %s:%d"
            ),

            // Issue::CLASS_CONTEXT
            new Issue(self::NonStaticSelf, self::CLASS_CONTEXT, self::SEVERITY_CRITICAL,
                "Reference to self when not in object context"
            ),
            new Issue(self::ContextNotObject, self::CLASS_CONTEXT, self::SEVERITY_CRITICAL,
                "Cannot access %s when not in object context"
            ),

            // Issue::CLASS_DEPRECATED
            new Issue(self::DeprecatedFunction, self::CLASS_DEPRECATED, self::SEVERITY_NORMAL,
                "Call to deprecated function %s() defined at %s:%d"
            ),

            // Issue::CLASS_PARAMETER
            new Issue(self::ParamReqAfterOpt, self::CLASS_PARAMETER, self::SEVERITY_LOW,
                "Required argument follows optional"
            ),
            new Issue(self::ParamTooMany, self::CLASS_PARAMETER, self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s) defined at %s:%d"
            ),
            new Issue(self::ParamTooManyInternal, self::CLASS_PARAMETER, self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s)"
            ),
            new Issue(self::ParamTooFew, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s) defined at %s:%d"
            ),
            new Issue(self::ParamTooFewInternal, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s)"
            ),
            new Issue(self::ParamSpecial1, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when argument %d is %s"
            ),
            new Issue(self::ParamSpecial2, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when passed only one argument"
            ),
            new Issue(self::ParamSpecial3, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "The last argument to %s must be of type %s"
            ),
            new Issue(self::ParamSpecial4, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "The second to last argument to %s must be of type %s"
            ), // TODO: get rid of this
            new Issue(self::ParamTypeMismatch, self::CLASS_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d is %s but %s() takes %s"
            ), // TODO: should be a type error. Merge with something else

            // Issue::CLASS_NOOP
            new Issue(self::NoopProperty, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Unused property"
            ),
            new Issue(self::NoopArray, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Unused array"
            ),
            new Issue(self::NoopConstant, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Unused constant"
            ),
            new Issue(self::NoopClosure, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Unused closure"
            ),
            new Issue(self::NoopVariable, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Unused variable"
            ),
            new Issue(self::NoopZeroReferences, self::CLASS_NOOP, self::SEVERITY_LOW,
                "Possibly zero references to %s"
            ),

            // Issue::CLASS_REDEFINE
            new Issue(self::RedefineClass, self::CLASS_REDEFINE, self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s at %s:%d"
            ),
            new Issue(self::RedefineClassInternal, self::CLASS_REDEFINE, self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s internally"
            ),
            new Issue(self::RedefineFunction, self::CLASS_REDEFINE, self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined at %s:%d"
            ),
            new Issue(self::RedefineFunctionInternal, self::CLASS_REDEFINE, self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined internally"
            ),

            // Issue::CLASS_ACCESS
            new Issue(self::AccessPropertyProtected, self::CLASS_ACCESS, self::SEVERITY_CRITICAL,
                "Cannot access protected property %s"
            ),
            new Issue(self::AccessPropertyPrivate, self::CLASS_ACCESS, self::SEVERITY_CRITICAL,
                "Cannot access private property %s"
            ),

            // Issue::CLASS_COMPATIBLE
            new Issue(self::CompatiblePHP7, self::CLASS_COMPATIBLE, self::SEVERITY_NORMAL,
                "Expression may not be PHP 7 compatible"
            ),
            new Issue(self::CompatibleExpressionPHP7, self::CLASS_COMPATIBLE, self::SEVERITY_NORMAL,
                "%s expression may not be PHP 7 compatible"
            ),

        ];

        $error_map = [];
        foreach ($error_list as $i => $error) {
            $error_map[$error->getType()] = $error;
        }

        return $error_map;
    }

    /**
     * @return string
     */
    public function getType() : string {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getClass() : int {
        return $this->class;
    }

    /**
     * @return int
     */
    public function getSeverity() : int {
        return $this->severity;
    }

    /**
     * @return string
     */
    public function getTemplate() : string {
        return $this->template;
    }

    /**
     * @return IssueInstance
     */
    public function __invoke(
        string $file,
        int $line,
        array $template_parameters = []
    ) : IssueInstance {
        return new IssueInstance(
            $this,
            $file,
            $line,
            $template_parameters
        );
    }

    /**
     * return Issue
     */
    public static function fromType(string $type) : Issue {
        $error_map = self::errorMap();

        assert(!empty($error_map[$type]),
            "Undefined error type $type");

        return $error_map[$type];
    }

    /**
     * @return void
     */
    public static function emit(
        string $type,
        string $file,
        int $line,
        ...$template_parameters
    ) {
        self::fromType($type)(
            $file,
            $line,
            $template_parameters
        )();
    }

}
