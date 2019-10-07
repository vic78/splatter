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
use PhpParser\Node\Arg;
use PhpParser\Node\Name;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;

$code = <<<'CODE'
<?php
($z ** $k) ** $y  * $x;
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
            return new Plus(
                new Mul(
                    new Mul(
                        clone $inputExpr->right,
                        new Pow(
                            clone $inputExpr->left,
                            new Minus(
                                clone $inputExpr->right,
                                new LNumber(1)
                            )
                        )
                    ),
                    differentiate($inputExpr->left)
                ),
                new Mul(
                    new Mul(
                        clone $inputExpr,
                        new FuncCall(new Name('log'), [new Arg(clone $inputExpr->left)])
                    ),
                    differentiate($inputExpr->right)
                )
            );
        case Variable::class:
            if ($inputExpr->name === $diffVar) {
                return new LNumber(1);
            } else {
                return new LNumber(0);
            }
        case LNumber::class:
        case DNumber::class:
            return new LNumber(0);
        case FuncCall::class:
            /** @var FuncCall $name */
            $name = $inputExpr->name;
            if ($name->isUnqualified()) {

                $argument = clone $inputExpr->args[0];
                $functionName = $name->getFirst();
                switch ($functionName) {
                    case 'acos':
                        $functionDerivative = new UnaryMinus(
                            new Div(
                                new LNumber(1),
                                new FuncCall(new Name('sqrt'), [
                                    new Minus(
                                        new LNumber(1),
                                        new Pow(
                                            clone $argument->value,
                                            new LNumber(2)
                                        )
                                    )
                                ])
                            )
                        );
                        break;
                    case 'acosh':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new FuncCall(new Name('sqrt'), [
                                new Minus(
                                    new Pow(
                                        clone $argument->value,
                                        new LNumber(2)
                                    ),
                                    new LNumber(1)
                                )
                            ])
                        );
                        break;
                    case 'asin':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new FuncCall(new Name('sqrt'), [
                                new Minus(
                                    new LNumber(1),
                                    new Pow(
                                        clone $argument->value,
                                        new LNumber(2)
                                    )
                                )
                            ])
                        );
                        break;
                    case 'asinh':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new FuncCall(new Name('sqrt'), [
                                new Plus(
                                    new Pow(
                                        clone $argument->value,
                                        new LNumber(2)
                                    ),
                                    new LNumber(1)
                                )
                            ])
                        );
                        break;
                    case 'atan':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Plus(
                                new LNumber(1),
                                new Pow(
                                    clone $argument->value,
                                    new LNumber(2)
                                )
                            )
                        );
                        break;
                    case 'atanh':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Minus(
                                new LNumber(1),
                                new Pow(
                                    clone $argument->value,
                                    new LNumber(2)
                                )
                            )
                        );
                        break;
                    case 'cos':
                        $functionDerivative = new UnaryMinus(new FuncCall(new Name('sin'), [$argument]));
                        break;
                    case 'cosh':
                        $functionDerivative = new FuncCall(new Name('sinh'), [$argument]);
                        break;
                    case 'exp':
                    case 'expm1':
                        $functionDerivative = new FuncCall(new Name('exp'), [$argument]);
                        break;
                    case 'log10':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Mul(
                                clone $argument->value,
                                new FuncCall(new Name('log'), [new Arg(new LNumber(10))])
                            )
                        );
                        break;
                    case 'log1p':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Plus(
                                new LNumber(1),
                                clone $argument->value,
                            )
                        );
                        break;
                    case 'log':
                        $functionDerivative = new Div(new LNumber(1), $argument->value);
                        break;
                    case 'pi':
                        $functionDerivative = new LNumber(0);
                        break;
                    case 'pow':
                        if (isset($inputExpr->args[1])) {
                            $secondArgument = clone $inputExpr->args[1];
                        } else {
                            throw RuntimeException('pow() function expects two parameters.');
                        }
                        return new Plus(
                            new Mul(
                                new Mul(
                                    clone $secondArgument->value,
                                    new FuncCall(new Name('pow'), [
                                        $argument,
                                        new Arg(new Minus(
                                            clone $secondArgument->value,
                                            new LNumber(1)
                                        ))])
                                ),
                                differentiate($argument->value)
                            ),
                            new Mul(
                                new Mul(
                                    clone $inputExpr,
                                    new FuncCall(new Name('log'), [$argument])
                                ),
                                differentiate($secondArgument->value)
                            )
                        );
                        break;
                    case 'sin':
                        $functionDerivative = new FuncCall(new Name('cos'), [$argument]);
                        break;
                    case 'sinh':
                        $functionDerivative = new FuncCall(new Name('cosh'), [$argument]);
                        break;
                    case 'sqrt':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Mul(
                                new LNumber(2),
                                new FuncCall(new Name('sqrt'), [$argument]),
                            )
                        );
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
                    case 'tanh':
                        $functionDerivative = new Div(
                            new LNumber(1),
                            new Pow(
                                new FuncCall(new Name('cosh'), [$argument]),
                                new LNumber(2)
                            )
                        );
                        break;
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
        case FuncCall::class:
            $name = $inputExpr->name->getFirst();
            $arguments = [];
            foreach ($inputExpr->args as $index => $arg) {
                $arguments[$index] = new Arg(simplify($arg->value));
            }
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
            if (!isZero($left) && isZero($right)) {
                return new LNumber(1);
            } elseif (isZero($left) && !isZero($right)) {
                return new LNumber(0);
            } elseif (isZero($left) && isZero($right)) {
                throw RuntimeException('Indeterminate expression 0**0.');
            } elseif (isUnity($left) || isZero($right)) {
                return new LNumber(1);
            } elseif (isInteger($left) && isInteger($right) &&
                $right->value >= 0) {
                return new LNumber(pow($left->value, $right->value));
            } elseif ($left instanceof Pow) {
                $innerLeft = $left->left;
                $innerRight = $left->right;
                return new Pow($innerLeft, simplify(new Mul($innerRight, $right)));
            } elseif ($left instanceof FuncCall &&
                    $left->name instanceof Name &&
                    $left->name->getFirst() === 'pow') {
                $innerLeft = $left->args[0]->value;
                $innerRight = $left->args[1]->value;
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
        case FuncCall::class:
            switch ($name) {
                case 'pow':
                    if (!isset($arguments[1])) {
                        throw RuntimeException('pow() function expects two parameters.');
                    } elseif (!isZero($arguments[0]->value) && isZero($arguments[1]->value)) {
                        return new LNumber(1);
                    } elseif (isZero($arguments[0]->value) && !isZero($arguments[1]->value)) {
                        return new LNumber(0);
                    } elseif (isZero($arguments[0]->value) && isZero($arguments[1]->value)) {
                        throw RuntimeException('Indeterminate expression 0**0.');
                    } elseif (isUnity($arguments[1]->value)) {
                        return clone $arguments[0]->value;
                    } elseif (isInteger($base = $arguments[0]->value) &&
                        isInteger($degree = $arguments[1]->value) &&
                        $degree->value >= 0) {
                        return new LNumber(pow($base->value, $degree->value));
                    } elseif ($arguments[0]->value instanceof Pow) {
                        $innerLeft = $arguments[0]->value->left;
                        $innerRight = $arguments[0]->value->right;
                        return new FuncCall(new Name('pow'), [new Arg($innerLeft), new Arg(simplify(new Mul($innerRight, $arguments[1]->value)))]);
                    } elseif ($arguments[0]->value instanceof FuncCall &&
                        $arguments[0]->value->name instanceof Name &&
                        $arguments[0]->value->name->getFirst() === 'pow') {
                        $innerLeft = $arguments[0]->value->args[0]->value;
                        $innerRight = $arguments[0]->value->args[1]->value;
                        return new FuncCall(new Name('pow'), [new Arg($innerLeft), new Arg(simplify(new Mul($innerRight, $arguments[1]->value)))]);
                    } else {
                        return new FuncCall(new Name('pow'), $arguments);
                    }
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
