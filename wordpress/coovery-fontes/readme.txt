=== Coovery Fontes ===

Cadastra a URL de cada filme e episódio. Essa URL só sai do site para uma conta do Firebase.

Instalação no coovery.com.br:

1. Compacte a pasta `coovery-fontes` em um zip.
2. No WordPress, vá em Plugins, Enviar plugin, e instale esse zip.
3. Ative "Coovery Fontes".
4. Abra o menu Fontes Coovery.
5. Confira se o id do projeto Firebase está `coovery` e clique em Buscar chaves do Firebase.
6. Adicione cada filme ou episódio com o id do TMDB e a URL do vídeo.

A consulta usada pelo app é:

https://coovery.com.br/wp-json/coovery/v1/stream?type=movie&tmdb_id=603

Para série, acrescente season e episode. O pedido precisa do cabeçalho Authorization: Bearer com o token do Firebase. Sem esse token a resposta é 401 e nenhuma URL é enviada.
