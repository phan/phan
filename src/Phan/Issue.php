<?php declare(strict_types=1);
namespace Phan;

/**
 * An issue emitted during the course of analysis
 */
class Issue {

    // Issue::CATEGORY_UNDEFINED
    const EmptyFile                 = 'PhanEmptyFile';
    const ParentlessClass           = 'PhanParentlessClass';
    const TraitParentReference      = 'PhanTraitParentReference';
    const UndeclaredClass           = 'PhanUndeclaredClass';
    const UndeclaredClassCatch      = 'PhanUndeclaredClassCatch';
    const UndeclaredClassConstant   = 'PhanUndeclaredClassConstant';
    const UndeclaredClassInstanceof = 'PhanUndeclaredClassInstanceof';
    const UndeclaredClassMethod     = 'PhanUndeclaredClassMethod';
    const UndeclaredClassReference  = 'PhanUndeclaredClassReference';
    const UndeclaredConstant        = 'PhanUndeclaredConstant';
    const UndeclaredExtendedClass   = 'PhanUndeclaredExtendedClass';
    const UndeclaredFunction        = 'PhanUndeclaredFunction';
    const UndeclaredInterface       = 'PhanUndeclaredInterface';
    const UndeclaredMethod          = 'PhanUndeclaredMethod';
    const UndeclaredProperty        = 'PhanUndeclaredProperty';
    const UndeclaredStaticMethod    = 'PhanUndeclaredStaticMethod';
    const UndeclaredStaticProperty  = 'PhanUndeclaredStaticProperty';
    const UndeclaredTrait           = 'PhanUndeclaredTrait';
    const UndeclaredTypeParameter   = 'PhanUndeclaredTypeParameter';
    const UndeclaredTypeProperty    = 'PhanUndeclaredTypeProperty';
    const UndeclaredVariable        = 'PhanUndeclaredVariable';

    // Issue::CATEGORY_TYPE
    const NonClassMethodCall        = 'PhanNonClassMethodCall';
    const TypeArrayOperator         = 'PhanTypeArrayOperator';
    const TypeArraySuspicious       = 'PhanTypeArraySuspicious';
    const TypeComparisonFromArray   = 'PhanTypeComparisonFromArray';
    const TypeComparisonToArray     = 'PhanTypeComparisonToArray';
    const TypeConversionFromArray   = 'PhanTypeConversionFromArray';
    const TypeInstantiateAbstract   = 'PhanTypeInstantiateAbstract';
    const TypeInstantiateInterface  = 'PhanTypeInstantiateInterface';
    const TypeInvalidLeftOperand    = 'PhanTypeInvalidLeftOperand';
    const TypeInvalidRightOperand   = 'PhanTypeInvalidRightOperand';
    const TypeMismatchArgument      = 'PhanTypeMismatchArgument';
    const TypeMismatchArgumentInternal = 'PhanTypeMismatchArgumentInternal';
    const TypeMismatchDefault       = 'PhanTypeMismatchDefault';
    const TypeMismatchForeach       = 'PhanTypeMismatchForeach';
    const TypeMismatchProperty      = 'PhanTypeMismatchProperty';
    const TypeMismatchReturn        = 'PhanTypeMismatchReturn';
    const TypeMissingReturn         = 'PhanTypeMissingReturn';
    const TypeNonVarPassByRef       = 'PhanTypeNonVarPassByRef';
    const TypeParentConstructorCalled = 'PhanTypeParentConstructorCalled';

    // Issue::CATEGORY_ANALYSIS
    const Unanalyzable              = 'PhanUnanalyzable';

    // Issue::CATEGORY_VARIABLE
    const VariableUseClause         = 'PhanVariableUseClause';

    // Issue::CATEGORY_STATIC
    const StaticCallToNonStatic     = 'PhanStaticCallToNonStatic';

    // Issue::CATEGORY_CONTEXT
    const ContextNotObject          = 'PhanContextNotObject';

    // Issue::CATEGORY_DEPRECATED
    const DeprecatedFunction        = 'PhanDeprecatedFunction';

    // Issue::CATEGORY_PARAMETER
    const ParamReqAfterOpt          = 'PhanParamReqAfterOpt';
    const ParamSpecial1             = 'PhanParamSpecial1';
    const ParamSpecial2             = 'PhanParamSpecial2';
    const ParamSpecial3             = 'PhanParamSpecial3';
    const ParamSpecial4             = 'PhanParamSpecial4';
    const ParamTooFew               = 'PhanParamTooFew';
    const ParamTooFewInternal       = 'PhanParamTooFewInternal';
    const ParamTooMany              = 'PhanParamTooMany';
    const ParamTooManyInternal      = 'PhanParamTooManyInternal';
    const ParamTypeMismatch         = 'PhanParamTypeMismatch';

    // Issue::CATEGORY_NOOP
    const NoopArray                 = 'PhanNoopArray';
    const NoopClosure               = 'PhanNoopClosure';
    const NoopConstant              = 'PhanNoopConstant';
    const NoopProperty              = 'PhanNoopProperty';
    const NoopVariable              = 'PhanNoopVariable';
    const UnreferencedClass         = 'PhanUnreferencedClass';
    const UnreferencedMethod        = 'PhanUnreferencedMethod';
    const UnreferencedProperty      = 'PhanUnreferencedProperty';
    const UnreferencedConstant      = 'PhanUnreferencedConstant';

    // Issue::CATEGORY_REDEFINE
    const RedefineClass             = 'PhanRedefineClass';
    const RedefineClassInternal     = 'PhanRedefineClassInternal';
    const RedefineFunction          = 'PhanRedefineFunction';
    const RedefineFunctionInternal  = 'PhanRedefineFunctionInternal';

    // Issue::CATEGORY_ACCESS
    const AccessPropertyPrivate     = 'PhanAccessPropertyPrivate';
    const AccessPropertyProtected   = 'PhanAccessPropertyProtected';

    // Issue::CATEGORY_COMPATIBLE
    const CompatibleExpressionPHP7  = 'PhanCompatibleExpressionPHP7';
    const CompatiblePHP7            = 'PhanCompatiblePHP7';

	const CATEGORY_ACCESS            = 1<<1;
	const CATEGORY_ANALYSIS          = 1<<2;
	const CATEGORY_COMPATIBLE        = 1<<3;
	const CATEGORY_CONTEXT           = 1<<4;
	const CATEGORY_DEPRECATED        = 1<<5;
	const CATEGORY_NOOP              = 1<<6;
	const CATEGORY_PARAMETER         = 1<<7;
	const CATEGORY_REDEFINE          = 1<<8;
	const CATEGORY_STATIC            = 1<<9;
	const CATEGORY_TYPE              = 1<<10;
	const CATEGORY_UNDEFINED         = 1<<11;
	const CATEGORY_VARIABLE          = 1<<12;

    const CATEGORY_NAME = [
        self::CATEGORY_ACCESS            => 'AccessError',
        self::CATEGORY_ANALYSIS          => 'Analysis',
        self::CATEGORY_COMPATIBLE        => 'CompatError',
        self::CATEGORY_CONTEXT           => 'Context',
        self::CATEGORY_DEPRECATED        => 'DeprecatedError',
        self::CATEGORY_NOOP              => 'NOOPError',
        self::CATEGORY_PARAMETER         => 'ParamError',
        self::CATEGORY_REDEFINE          => 'RedefineError',
        self::CATEGORY_STATIC            => 'StaticCallError',
        self::CATEGORY_TYPE              => 'TypeError',
        self::CATEGORY_UNDEFINED         => 'UndefError',
        self::CATEGORY_VARIABLE          => 'VarError',
    ];

    const SEVERITY_LOW      = 0;
    const SEVERITY_NORMAL   = 5;
    const SEVERITY_CRITICAL = 10;

    /** @var string */
    private $type;

    /** @var int */
    private $category;

    /** @var int */
    private $severity;

    /** @var string */
    private $template;


    /**
     * @param string $type
     * @param int $category
     * @param int $severity
     * @param string $template
     */
    public function __construct(
        string $type,
        int $category,
        int $severity,
        string $template
    ) {
        $this->type = $type;
        $this->category = $category;
        $this->severity = $severity;
        $this->template = $template;
    }

    /**
     * @return Issue[]
     */
    public static function issueMap() {
        static $error_map;

        if (!empty($error_map)) {
            return $error_map;
        }

        $error_list = [

            // Issue::CATEGORY_UNDEFINED
            new Issue(self::EmptyFile, self::CATEGORY_UNDEFINED, self::SEVERITY_LOW,
                "Empty file %s"
            ),
            new Issue(self::ParentlessClass, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to parent of class %s that does not extend anything"
            ),
            new Issue(self::UndeclaredClass, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to undeclared class %s"
            ),
            new Issue(self::UndeclaredExtendedClass, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class extends undeclared class %s"
            ),
            new Issue(self::UndeclaredInterface, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class implements undeclared interface %s"
            ),
            new Issue(self::UndeclaredTrait, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Class uses undeclared trait %s"
            ),
            new Issue(self::UndeclaredClassCatch, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Catching undeclared class %s"
            ),
            new Issue(self::UndeclaredClassConstant, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Reference to constant %s from undeclared class %s"
            ),
            new Issue(self::UndeclaredClassInstanceof, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Checking instanceof against undeclared class %s"
            ),
            new Issue(self::UndeclaredClassMethod, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Call to method %s from undeclared class %s"
            ),
            new Issue(self::UndeclaredClassReference, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared class %s"
            ),
            new Issue(self::UndeclaredConstant, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared constant %s"
            ),
            new Issue(self::UndeclaredFunction, self::CATEGORY_UNDEFINED, self::SEVERITY_CRITICAL,
                "Call to undeclared function %s"
            ),
            new Issue(self::UndeclaredMethod, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Call to undeclared method %s"
            ),
            new Issue(self::UndeclaredStaticMethod, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Static call to undeclared method %s"
            ),
            new Issue(self::UndeclaredProperty, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Reference to undeclared property %s"
            ),
            new Issue(self::UndeclaredStaticProperty, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Static property '%s' on %s is undeclared"
            ),
            new Issue(self::TraitParentReference, self::CATEGORY_UNDEFINED, self::SEVERITY_LOW,
                "Reference to parent from trait %s"
            ),
            new Issue(self::UndeclaredVariable, self::CATEGORY_UNDEFINED, self::SEVERITY_LOW,
                "Variable \$%s is undeclared"
            ),
            new Issue(self::UndeclaredTypeParameter, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Parameter of undeclared type %s"
            ),
            new Issue(self::UndeclaredTypeProperty, self::CATEGORY_UNDEFINED, self::SEVERITY_NORMAL,
                "Property of undeclared type %s"
            ),

            // Issue::CATEGORY_ANALYSIS
            new Issue(self::Unanalyzable, self::CATEGORY_UNDEFINED, self::SEVERITY_LOW,
                "Expression is unanalyzable or feature is unimplemented. Please create an issue at https://github.com/etsy/phan/issues/new."
            ),

            // Issue::CATEGORY_TYPE
            new Issue(self::TypeMismatchProperty, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Assigning %s to property but %s is %s"
            ),
            new Issue(self::TypeMismatchDefault, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Default value for %s \$%s can't be %s"
            ),
            new Issue(self::TypeMismatchArgument, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s defined at %s:%d"
            ),
            new Issue(self::TypeMismatchArgumentInternal, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s"
            ),
            new Issue(self::TypeMismatchReturn, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Returning type %s but %s() is declared to return %s"
            ),
            new Issue(self::TypeMissingReturn, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Method %s is declared to return %s but has no return value"
            ),
            new Issue(self::TypeMismatchForeach, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "%s passed to foreach instead of array"
            ),
            new Issue(self::TypeArrayOperator, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Invalid array operator"
            ),
            new Issue(self::TypeArraySuspicious, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Suspicious array access to %s"
            ),
            new Issue(self::TypeComparisonToArray, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "%s to array comparison"
            ),
            new Issue(self::TypeComparisonFromArray, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "array to %s comparison"
            ),
            new Issue(self::TypeConversionFromArray, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "array to %s conversion"
            ),
            new Issue(self::TypeInstantiateAbstract, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Instantiation of abstract class %s"
            ),
            new Issue(self::TypeInstantiateInterface, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Instantiation of interface %s"
            ),
            new Issue(self::TypeInvalidRightOperand, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Invalid operator: left operand is array and right is not"
            ),
            new Issue(self::TypeInvalidLeftOperand, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Invalid operator: right operand is array and left is not"
            ),
            new Issue(self::TypeParentConstructorCalled, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Must call parent::__construct() from %s which extends %s"
            ),
            new Issue(self::TypeNonVarPassByRef, self::CATEGORY_TYPE, self::SEVERITY_NORMAL,
                "Only variables can be passed by reference at argument %d of %s()"
            ),
            new Issue(self::NonClassMethodCall, self::CATEGORY_TYPE, self::SEVERITY_CRITICAL,
                "Call to method %s on non-class type %s"
            ),

            // Issue::CATEGORY_VARIABLE
            new Issue(self::VariableUseClause, self::CATEGORY_VARIABLE, self::SEVERITY_CRITICAL,
                "Non-variables not allowed within use clause"
            ),

            // Issue::CATEGORY_STATIC
            new Issue(self::StaticCallToNonStatic, self::CATEGORY_STATIC, self::SEVERITY_NORMAL,
                "Static call to non-static method %s defined at %s:%d"
            ),

            // Issue::CATEGORY_CONTEXT
            new Issue(self::ContextNotObject, self::CATEGORY_CONTEXT, self::SEVERITY_CRITICAL,
                "Cannot access %s when not in object context"
            ),

            // Issue::CATEGORY_DEPRECATED
            new Issue(self::DeprecatedFunction, self::CATEGORY_DEPRECATED, self::SEVERITY_NORMAL,
                "Call to deprecated function %s() defined at %s:%d"
            ),

            // Issue::CATEGORY_PARAMETER
            new Issue(self::ParamReqAfterOpt, self::CATEGORY_PARAMETER, self::SEVERITY_LOW,
                "Required argument follows optional"
            ),
            new Issue(self::ParamTooMany, self::CATEGORY_PARAMETER, self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s) defined at %s:%d"
            ),
            new Issue(self::ParamTooManyInternal, self::CATEGORY_PARAMETER, self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s)"
            ),
            new Issue(self::ParamTooFew, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s) defined at %s:%d"
            ),
            new Issue(self::ParamTooFewInternal, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s)"
            ),
            new Issue(self::ParamSpecial1, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when argument %d is %s"
            ),
            new Issue(self::ParamSpecial2, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when passed only one argument"
            ),
            new Issue(self::ParamSpecial3, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "The last argument to %s must be of type %s"
            ),
            new Issue(self::ParamSpecial4, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "The second to last argument to %s must be of type %s"
            ),
            new Issue(self::ParamTypeMismatch, self::CATEGORY_PARAMETER, self::SEVERITY_NORMAL,
                "Argument %d is %s but %s() takes %s"
            ),

            // Issue::CATEGORY_NOOP
            new Issue(self::NoopProperty, self::CATEGORY_NOOP, self::SEVERITY_LOW,
                "Unused property"
            ),
            new Issue(self::NoopArray, self::CATEGORY_NOOP, self::SEVERITY_LOW,
                "Unused array"
            ),
            new Issue(self::NoopConstant, self::CATEGORY_NOOP, self::SEVERITY_LOW,
                "Unused constant"
            ),
            new Issue(self::NoopClosure, self::CATEGORY_NOOP, self::SEVERITY_LOW,
                "Unused closure"
            ),
            new Issue(self::NoopVariable, self::CATEGORY_NOOP, self::SEVERITY_LOW,
                "Unused variable"
            ),
            new Issue(self::UnreferencedClass, self::CATEGORY_NOOP, self::SEVERITY_CRITICAL,
                "Possibly zero references to class %s"
            ),
            new Issue(self::UnreferencedMethod, self::CATEGORY_NOOP, self::SEVERITY_CRITICAL,
                "Possibly zero references to method %s"
            ),
            new Issue(self::UnreferencedProperty, self::CATEGORY_NOOP, self::SEVERITY_CRITICAL,
                "Possibly zero references to property %s"
            ),
            new Issue(self::UnreferencedConstant, self::CATEGORY_NOOP, self::SEVERITY_CRITICAL,
                "Possibly zero references to constant %s"
            ),

            // Issue::CATEGORY_REDEFINE
            new Issue(self::RedefineClass, self::CATEGORY_REDEFINE, self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s at %s:%d"
            ),
            new Issue(self::RedefineClassInternal, self::CATEGORY_REDEFINE, self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s internally"
            ),
            new Issue(self::RedefineFunction, self::CATEGORY_REDEFINE, self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined at %s:%d"
            ),
            new Issue(self::RedefineFunctionInternal, self::CATEGORY_REDEFINE, self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined internally"
            ),

            // Issue::CATEGORY_ACCESS
            new Issue(self::AccessPropertyProtected, self::CATEGORY_ACCESS, self::SEVERITY_CRITICAL,
                "Cannot access protected property %s"
            ),
            new Issue(self::AccessPropertyPrivate, self::CATEGORY_ACCESS, self::SEVERITY_CRITICAL,
                "Cannot access private property %s"
            ),

            // Issue::CATEGORY_COMPATIBLE
            new Issue(self::CompatiblePHP7, self::CATEGORY_COMPATIBLE, self::SEVERITY_NORMAL,
                "Expression may not be PHP 7 compatible"
            ),
            new Issue(self::CompatibleExpressionPHP7, self::CATEGORY_COMPATIBLE, self::SEVERITY_NORMAL,
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
    public function getCategory() : int {
        return $this->category;
    }

    /**
     * @return string
     * The name of the category
     */
    public static function getNameForCategory(int $category) : string {
        return self::CATEGORY_NAME[$category] ?? '';
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
        $error_map = self::issueMap();

        assert(!empty($error_map[$type]),
            "Undefined error type $type");

        return $error_map[$type];
    }

    /**
     * @param string $type
     * The type of the issue
     *
     * @param string $file
     * The name of the file where the issue was found
     *
     * @param int $line
     * The line number (start) where the issue was found
     *
     * @param mixed $template_parameters
     * Any template parameters required for the issue
     * message
     *
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
