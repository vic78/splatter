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
6 * $x + 19 * $x + 4 + 23;

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
print_r($output = differentiate($expr), true);

function differentiate($inputExpr, string $diffVar = 'x')
{
    if (!is_object($inputExpr)) {
        throw new RuntimeException("Expression cannot be differentiated");
    }

    switch($inputExprClass = get_class($inputExpr)) {
        case Expression::class:
            $outputExpr = new Expression(differentiate($inputExpr->expr));
            break;
        case Plus::class:
            $outputExpr = new Plus(
                differentiate($inputExpr->left),
                differentiate($inputExpr->right)
            );
            break;
        case Minus::class:
            $outputExpr = new Minus(
                differentiate($inputExpr->left),
                differentiate($inputExpr->right)
            );
            break;
        case Mul::class:
            $outputExpr = new Plus(
                simplify(new Mul(differentiate($inputExpr->left), clone $inputExpr->right)),
                simplify(new Mul(clone $inputExpr->left, differentiate($inputExpr->right)))
            );
            break;
        case Div::class:
            $outputExpr = new Div(
                new Minus(
                        simplify(new Mul(differentiate($inputExpr->left), clone $inputExpr->right)),
                        simplify(new Mul(clone $inputExpr->left, differentiate($inputExpr->right)))
                    ),
                new Pow(clone $inputExpr->right, new LNumber(2))
            );
            break;
        case Pow::class:
            $outputExpr = simplify(new Mul(
                $inputExpr->right,
                new Pow(
                    $inputExpr->left,
                    new Minus($inputExpr->right, new LNumber(1))
                )
            ));
            break;
        case Variable::class:
            if ($inputExpr->name === $diffVar) {
                $outputExpr = new LNumber(1);
            } else {
                $outputExpr = new LNumber(0);
            }
            break;
        case LNumber::class:
            $outputExpr = new LNumber(0);
            break;
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
                }

                $outputExpr = new Mul($functionDerivative, differentiate($argument->value));
            }
            break;
        case UnaryMinus::class:
            $outputExpr = new UnaryMinus(differentiate($inputExpr->expr));
            break;
        case UnaryPlus::class:
            $outputExpr = differentiate($inputExpr->expr);
            break;
        default:
            throw new RuntimeException("Object of $inputExprClass cannot be differentiated");
    }

    return simplify($outputExpr);
}

function simplify($inputExpr)
{
    switch(get_class($inputExpr)) {
        case Plus::class:
            if (isZero($inputExpr->left)) {
                return clone $inputExpr->right;
            } elseif (isZero($inputExpr->right)) {
                return clone $inputExpr->left;
            } elseif (isInteger($inputExpr->left) && isInteger($inputExpr->right)) {
                return new LNumber($inputExpr->left->value + $inputExpr->right->value);
            }
        case Mul::class:
            if (isZero($inputExpr->left) || isZero($inputExpr->right)) {
                return new LNumber(0);
            } elseif (isUnity($inputExpr->left)) {
                return clone $inputExpr->right;
            } elseif (isUnity($inputExpr->right)) {
                return clone $inputExpr->left;
            } elseif (isInteger($inputExpr->left) && isInteger($inputExpr->right)) {
                return new LNumber($inputExpr->left->value * $inputExpr->right->value);
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
    if ($type === Lnumber::class ) {
        print_r("integer  " . $inputExpr->value. " ". gettype($inputExpr->value));
        
    }
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
