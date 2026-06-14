<?php
namespace App\Twig;

use Twig\Node\Node;
use Twig\Compiler;

class RolesNode extends Node
{
    public function __construct(array $tests, ?Node $else, int $lineno, string $tag)
    {
        $nodes = ['body' => $tests[1]];
        if ($else !== null) {
            $nodes['else'] = $else;
        }
        
        parent::__construct($nodes, ['roles' => $tests[0]], $lineno, $tag);
    }
    
    public function compile(Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('if (in_array($context["U_ROLE"] ?? null, [')
            ->raw(implode(', ', array_map(function($role) {
                return "'" . $role . "'";
            }, $this->getAttribute('roles'))))
            ->raw('])) {')
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
            ->write('}');
        
        if ($this->hasNode('else')) {
            $compiler
                ->write(' else {')
                ->indent()
                ->subcompile($this->getNode('else'))
                ->outdent()
                ->write('}');
        }
    }
}