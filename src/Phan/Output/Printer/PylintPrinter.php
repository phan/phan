<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PylintPrinter implements IssuePrinterInterface
{
    const TYPE_OFFSET_LOOKUP = [
        // Issue::CATEGORY_UNDEFINED
        Issue::EmptyFile => 0,
        Issue::ParentlessClass => 1,
        Issue::TraitParentReference => 2,
        Issue::UndeclaredClass => 3,
        Issue::UndeclaredClassCatch => 4,
        Issue::UndeclaredClassConstant => 5,
        Issue::UndeclaredClassInstanceof => 6,
        Issue::UndeclaredClassMethod => 7,
        Issue::UndeclaredClassReference => 8,
        Issue::UndeclaredConstant => 9,
        Issue::UndeclaredExtendedClass => 10,
        Issue::UndeclaredFunction => 11,
        Issue::UndeclaredInterface => 12,
        Issue::UndeclaredMethod => 13,
        Issue::UndeclaredProperty => 14,
        Issue::UndeclaredStaticMethod => 15,
        Issue::UndeclaredStaticProperty => 16,
        Issue::UndeclaredTrait => 17,
        Issue::UndeclaredTypeParameter => 18,
        Issue::UndeclaredTypeProperty => 19,
        Issue::UndeclaredVariable => 20,

        // Issue::CATEGORY_TYPE
        Issue::NonClassMethodCall => 0,
        Issue::TypeArrayOperator => 1,
        Issue::TypeArraySuspicious => 2,
        Issue::TypeComparisonFromArray => 3,
        Issue::TypeComparisonToArray => 4,
        Issue::TypeConversionFromArray => 5,
        Issue::TypeInstantiateAbstract => 6,
        Issue::TypeInstantiateInterface => 7,
        Issue::TypeInvalidLeftOperand => 8,
        Issue::TypeInvalidRightOperand => 9,
        Issue::TypeMismatchArgument => 10,
        Issue::TypeMismatchArgumentInternal => 11,
        Issue::TypeMismatchDefault => 12,
        Issue::TypeMismatchForeach => 13,
        Issue::TypeMismatchProperty => 14,
        Issue::TypeMismatchReturn => 15,
        Issue::TypeMissingReturn => 16,
        Issue::TypeNonVarPassByRef => 17,
        Issue::TypeParentConstructorCalled => 18,
        Issue::TypeVoidAssignment => 19,

        // Issue::CATEGORY_ANALYSIS
        Issue::Unanalyzable => 0,

        // Issue::CATEGORY_VARIABLE
        Issue::VariableUseClause => 0,

        // Issue::CATEGORY_STATIC
        Issue::StaticCallToNonStatic => 0,

        // Issue::CATEGORY_CONTEXT
        Issue::ContextNotObject => 0,

        // Issue::CATEGORY_DEPRECATED
        Issue::DeprecatedClass => 0,
        Issue::DeprecatedFunction => 1,
        Issue::DeprecatedProperty => 2,

        // Issue::CATEGORY_PARAMETER
        Issue::ParamReqAfterOpt => 0,
        Issue::ParamSpecial1 => 1,
        Issue::ParamSpecial2 => 2,
        Issue::ParamSpecial3 => 3,
        Issue::ParamSpecial4 => 4,
        Issue::ParamTooFew => 5,
        Issue::ParamTooFewInternal => 6,
        Issue::ParamTooMany => 7,
        Issue::ParamTooManyInternal => 8,
        Issue::ParamTypeMismatch => 9,
        Issue::ParamSignatureMismatch => 10,
        Issue::ParamSignatureMismatchInternal => 11,
        Issue::ParamRedefined => 12,

        // Issue::CATEGORY_NOOP
        Issue::NoopArray => 0,
        Issue::NoopClosure => 1,
        Issue::NoopConstant => 2,
        Issue::NoopProperty => 3,
        Issue::NoopVariable => 4,
        Issue::UnreferencedClass => 5,
        Issue::UnreferencedMethod => 6,
        Issue::UnreferencedProperty => 7,
        Issue::UnreferencedConstant => 8,

        // Issue::CATEGORY_REDEFINE
        Issue::RedefineClass => 0,
        Issue::RedefineClassInternal => 1,
        Issue::RedefineFunction => 2,
        Issue::RedefineFunctionInternal => 3,
        Issue::IncompatibleCompositionProp => 4,
        Issue::IncompatibleCompositionMethod => 5,

        // Issue::CATEGORY_ACCESS
        Issue::AccessPropertyPrivate => 0,
        Issue::AccessPropertyProtected => 1,
        Issue::AccessMethodPrivate => 2,
        Issue::AccessMethodProtected => 3,
        Issue::AccessSignatureMismatch => 4,
        Issue::AccessSignatureMismatchInternal => 5,
        Issue::AccessStaticToNonStatic => 6,
        Issue::AccessNonStaticToStatic => 7,

        // Issue::CATEGORY_COMPATIBLE
        Issue::CompatibleExpressionPHP7 => 0,
        Issue::CompatiblePHP7 => 1,

        // Issue::CATEGORY_GENERIC
        Issue::TemplateTypeConstant => 0,
        Issue::TemplateTypeStaticMethod => 1,
        Issue::TemplateTypeStaticProperty => 2,
        Issue::GenericGlobalVariable => 3,
        Issue::GenericConstructorTypes => 4,
    ];

    /** @var OutputInterface */
    private $output;

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance)
    {
        $message = sprintf(
            "%s: %s",
            $instance->getIssue()->getType(),
            $instance->getMessage()
        );
        $line = sprintf("%s:%d: [%s] %s",
            $instance->getFile(),
            $instance->getLine(),
            self::get_severity_code($instance),
            $message
        );

        $this->output->writeln($line);
    }

    public static function get_severity_code(IssueInstance $instance) : string
    {
        $issue = $instance->getIssue();
        $categoryId = self::get_readable_category_code($issue);
        switch($issue->getSeverity()) {
        case Issue::SEVERITY_LOW:
            return 'C' . $categoryId;
        case Issue::SEVERITY_NORMAL:
            return 'W' . $categoryId;
        case Issue::SEVERITY_CRITICAL:
            return 'E' . $categoryId;
        }
    }

    /**
     * @return int(log_2(category) + 1) * 1000 + offset, or 15000 + offset if category is unknown.
     */
    public static function get_readable_category_code(Issue $issue) : int {
        $category = $issue->getCategory();
        for ($i = 1; $i <= 14; $i++) {
            if ($category == (1 << $i)) {
                break;
            }
        }
        return $i * 1000 + self::get_type_index($issue);
    }

    /**
     * @return int - Int between 0 and 999. Returns 999 if issue type is unrecognized.
     */
    public static function get_type_index(Issue $issue) : int
    {
        return self::TYPE_OFFSET_LOOKUP[$issue->getType()] ?? 999;
    }

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
