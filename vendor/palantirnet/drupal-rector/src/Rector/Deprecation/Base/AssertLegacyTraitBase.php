<?php declare(strict_types=1);

namespace DrupalRector\Rector\Deprecation\Base;

use DrupalRector\Utility\AddCommentTrait;
use PhpParser\Node;
use Rector\Core\Rector\AbstractRector;

abstract class AssertLegacyTraitBase extends AbstractRector
{

    use AddCommentTrait;

    protected $comment = '';
    protected $deprecatedMethodName;
    protected $methodName;
    protected $isAssertSessionMethod = true;

    public function getNodeTypes(): array
    {
        return [
            Node\Expr\MethodCall::class,
        ];
    }

    protected function createAssertSessionMethodCall(string $method, array $args): Node\Expr\MethodCall
    {
        $assertSessionNode = $this->nodeFactory->createLocalMethodCall('assertSession');
        return $this->nodeFactory->createMethodCall($assertSessionNode, $method, $args);
    }

    public function refactor(Node $node): ?Node
    {
        assert($node instanceof Node\Expr\MethodCall);
        if ($this->getName($node->name) !== $this->deprecatedMethodName) {
            return null;
        }

        if ($this->comment !== '') {
            $this->addDrupalRectorComment($node, $this->comment);
        }

        $args = $this->processArgs($node->args);
        if ($this->isAssertSessionMethod) {
            return $this->createAssertSessionMethodCall($this->methodName, $args);
        }
        return $this->nodeFactory->createLocalMethodCall($this->methodName, $args);
    }

    protected function processArgs(array $args): array
    {
        return $args;
    }
}

