<?php

require_once 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr;


use PhpParser\Node\Stmt\Expression;

use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\BinaryOp\Pow;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\Div;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;

$code = <<<'CODE'
<?php
$x ** 4 + cos($x) * $x + tan($x);

CODE;

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
echo $dumper->dump($ast) . "\n";

$output = null;
$expr = $ast[0];
print_r($expr);
print_r($output = differentiate($expr));

function differentiate($inputExpr, string $diffVar = 'x')
{
    if (!is_object($inputExpr)) {
        return null;
    }

    switch(get_class($inputExpr)) {
        case Expression::class:
            return new Expression(differentiate($inputExpr->expr));
        case Plus::class:
            return new Plus(
                differentiate($inputExpr->left),
                differentiate($inputExpr->right)
            );
        case Plus::class:
            return new Plus(
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
                $inputExpr->right,
                new Pow(
                    $inputExpr->left,
                    new Minus($inputExpr->right, new LNumber(1))
                )
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
                }

                return new Mul($functionDerivative, differentiate($argument->value));
            }
        case UnaryMinus::class:
            return new UnaryMinus(differentiate($inputExpr->expr));
        case UnaryPlus::class:
            return differentiate($inputExpr->expr);
    }

    return null;
}

echo "\n";
use PhpParser\PrettyPrinter;

$prettyPrinter = new PrettyPrinter\Standard;
echo $prettyPrinter->prettyPrintFile([$output]);
