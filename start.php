<?php

require_once 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr;

//use RuntimeException;
use PhpParser\Node\Stmt\Expression;

use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\BinaryOp\Pow;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\Div;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;

$code = <<<'CODE'
<?php
log($x ** 4) + exp(sin($x));

CODE;

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
//echo $dumper->dump($ast) . "\n";

$output = null;
$expr = $ast[0];
//print_r($expr);
print_r($output = simplify(differentiate($expr)), true);

function differentiate($inputExpr, string $diffVar = 'x')
{
    if (!is_object($inputExpr)) {
        throw new RuntimeException("Expression cannot be differentiated");
    }

    switch($inputExprClass = get_class($inputExpr)) {

        case Expression::class:
            return new Expression(differentiate($inputExpr->expr));
        case Plus::class:
            return new Plus(
                differentiate($inputExpr->left),
                differentiate($inputExpr->right)
            );
        case Minus::class:
            return new Minus(
                differentiate($inputExpr->left),
                differentiate($inputExpr->right)
            );
        case Mul::class:
            return new Plus(
                new Mul(differentiate($inputExpr->left), clone $inputExpr->right),
                new Mul(clone $inputExpr->left, differentiate($inputExpr->right))
            );
        case Div::class:
            return new Div(
                new Minus(
                        new Mul(differentiate($inputExpr->left), clone $inputExpr->right),
                        new Mul(clone $inputExpr->left, differentiate($inputExpr->right))
                    ),
                new Pow(clone $inputExpr->right, new LNumber(2))
            );
        case Pow::class:
            return new Mul(
                new Mul(
                    $inputExpr->right,
                    new Pow(
                        $inputExpr->left,
                        new Minus($inputExpr->right, new LNumber(1))
                    ),
                ),
                differentiate($inputExpr->left)
            );
        case Variable::class:
            if ($inputExpr->name === $diffVar) {
                return new LNumber(1);
            } else {
                return new LNumber(0);
            }
        case LNumber::class:
            return new LNumber(0);
        case FuncCall::class:
            /** @var FuncCall $name */
            $name = $inputExpr->name;
            if ($name->isUnqualified()) {

                $argument = clone $inputExpr->args[0];
                $functionName = $name->getFirst();
                switch ($functionName) {
                    case 'sin':
                        $functionDerivative = new FuncCall(new Name('cos'), [$argument]);
                        break;
                    case 'cos':
                        $functionDerivative = new UnaryMinus(new FuncCall(new Name('sin'), [$argument]));
                        break;
                    case 'tan':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Pow(
                                new FuncCall(new Name('cos'), [$argument]),
                                new LNumber(2)
                            )
                        );
                        break;
                    case 'exp':
                        $functionDerivative = new FuncCall(new Name('exp'), [$argument]);
                        break;
                    case 'log':
                        $functionDerivative = new Div(new LNumber(1), $argument->value);
                }

                return new Mul($functionDerivative, differentiate($argument->value));
            }
        case UnaryMinus::class:
            return new UnaryMinus(differentiate($inputExpr->expr));
        case UnaryPlus::class:
            return differentiate($inputExpr->expr);
        default:
            throw new RuntimeException("Object of $inputExprClass cannot be differentiated");
    }
}

function simplify($inputExpr)
{
    $inputExprClass = get_class($inputExpr);

    switch ($inputExprClass) {

        case Expression::class:
        case UnaryPlus::class:
        case UnaryMinus::class:
            $expr = simplify($inputExpr->expr);
            break;
        case Plus::class:
        case Minus::class:
        case Mul::class:
        case Div::class:
        case Pow::class:
            $left  = simplify($inputExpr->left);
            $right = simplify($inputExpr->right);
            break;
    }

    switch ($inputExprClass) {

        case Expression::class:
            return new Expression($expr);
        case Plus::class:
            if (isZero($left)) {
                return clone $right;
            } elseif (isZero($right)) {
                return clone $left;
            } elseif (isInteger($left) && isInteger($right)) {
                return new LNumber($left->value + $right->value);
            } else {
                return new Plus($left, $right);
            }
        case Mul::class:
            if (isZero($left) || isZero($right)) {
                return new LNumber(0);
            } elseif (isUnity($left)) {
                return clone $right;
            } elseif (isUnity($right)) {
                return clone $left;
            } elseif (isInteger($left) && isInteger($right)) {
                return new LNumber($left->value * $right->value);
            } else {
                return new Mul($left, $right);
            }
        case Div::class:
            if (isZero($right)) {
                throw new RuntimeException('Division by zero');
            } elseif (isZero($left)) {
                return new LNumber(0);
            } elseif (isUnity($right)) {
                return clone $left;
            } elseif (  isInteger($left) &&
                        isInteger($right) &&
                        $left % $right === 0) {
                return new LNumber($left->value / $right->value);
            } else {
                return new Div($left, $right);
            }
        case Minus::class:
            if (isZero($left)) {
                return new UnaryMinus($right);
            } elseif (isZero($right)) {
                return clone $left;
            } elseif (isInteger($left) && isInteger($right)) {
                return new LNumber($left->value - $right->value);
            } else {
                return new Minus($left, $right);
            }
        case Pow::class:
            if (isZero($left)) {
                return new LNumber(0);
            } elseif (isUnity($left) || isZero($right)) {
                return new LNumber(1);
            } elseif ($left instanceof Pow) {
                $innerLeft = $left->left;
                $innerRight = $left->right;
                return new Pow($innerLeft, simplify(new Mul($innerRight, $right)));
            } else {
                return new Pow($left, $right);
            }

        case UnaryPlus::class:
            return $expr;
        case UnaryMinus::class:
            if ($expr instanceof UnaryMinus) {
                return clone $expr->expr;
            } else {
                return $inputExpr;
            }
    }

    return $inputExpr;
}

function isZero($inputExpr)
{
    $type = get_class($inputExpr);
    return ($type === Lnumber::class || $type === DNumber::class) &&
           empty($inputExpr->value);
}

function isUnity($inputExpr)
{
    $type = get_class($inputExpr);
    return  $type === Lnumber::class && $inputExpr->value === 1 ||
            $type === DNumber::class && (float)$inputExpr->value === 1.0;
}

function isInteger($inputExpr)
{
    return get_class($inputExpr) === Lnumber::class;
}


echo "\n";
use PhpParser\PrettyPrinter;

$prettyPrinter = new PrettyPrinter\Standard;
echo $prettyPrinter->prettyPrintFile([$output]);
