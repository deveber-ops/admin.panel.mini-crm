<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TokenParser\AbstractTokenParser;
use Twig\Token;

class RolesExtension extends AbstractExtension
{
    public function getTokenParsers()
    {
        return [
            new class extends AbstractTokenParser {
                public function parse(Token $token)
                {
                    $lineno = $token->getLine();
                    $stream = $this->parser->getStream();
                    
                    // Получаем список ролей
                    $roles = [];
                    while (!$stream->test(Token::BLOCK_END_TYPE)) {
                        $roles[] = $stream->expect(Token::STRING_TYPE)->getValue();
                        if (!$stream->test(Token::BLOCK_END_TYPE)) {
                            $stream->expect(Token::PUNCTUATION_TYPE, ',');
                        }
                    }
                    
                    $stream->expect(Token::BLOCK_END_TYPE);
                    $body = $this->parser->subparse([$this, 'decideRolesFork']);
                    
                    $tests = [$roles, $body];
                    $else = null;
                    
                    $end = false;
                    while (!$end) {
                        switch ($stream->next()->getValue()) {
                            case 'else':
                                $stream->expect(Token::BLOCK_END_TYPE);
                                $else = $this->parser->subparse([$this, 'decideRolesEnd']);
                                break;
                            case 'endroles':
                                $end = true;
                                break;
                            default:
                                throw new \Twig\Error\SyntaxError(sprintf('Unexpected end of template. Twig was looking for the following tags "else", "endroles" to close the "roles" block started at line %d).', $lineno), $stream->getCurrent()->getLine(), $stream->getSourceContext());
                        }
                    }
                    
                    $stream->expect(Token::BLOCK_END_TYPE);
                    return new RolesNode($tests, $else, $lineno, $this->getTag());
                }
                
                public function decideRolesFork(Token $token)
                {
                    return $token->test(['else', 'endroles']);
                }
                
                public function decideRolesEnd(Token $token)
                {
                    return $token->test(['endroles']);
                }
                
                public function getTag()
                {
                    return 'roles';
                }
            }
        ];
    }
}