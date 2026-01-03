<?php

namespace App\Api;

use Sophia\Controller\Controller;
use Sophia\Controller\Get;
use Sophia\Controller\Post;
use Sophia\Controller\Put;
use Sophia\Controller\Delete;

/**
 * 🔥 Controller esempio per gestire i Post
 *
 * Utilizzo nelle routes:
 * [
 *     'path' => 'posts',
 *     'controller' => PostController::class,
 * ]
 *
 * Richieste supportate:
 * - GET    /posts           -> findAll()
 * - GET    /posts/search    -> search()
 * - GET    /posts/12        -> findOne($id)
 * - GET    /posts/12/detail -> getDetail($id)
 * - POST   /posts           -> create()
 * - PUT    /posts/12        -> update($id)
 * - DELETE /posts/12        -> remove($id)
 */
#[Controller('posts')]  // 🔥 DECORATORE OBBLIGATORIO
class PostController
{
    /**
     * GET /posts
     * Lista tutti i post
     */
    #[Get()]
    public function findAll(): array
    {
        // Simula il recupero dal database
        return [
            'success' => true,
            'data' => [
                ['id' => 1, 'title' => 'Primo Post', 'body' => 'Contenuto...'],
                ['id' => 2, 'title' => 'Secondo Post', 'body' => 'Altro contenuto...'],
                ['id' => 3, 'title' => 'Terzo Post', 'body' => 'Ancora contenuto...'],
            ]
        ];
    }

    /**
     * GET /posts/search
     * Ricerca post per query
     *
     * ⚠️ IMPORTANTE: Questa route statica DEVE essere definita
     * PRIMA della route dinamica ':id' altrimenti non verrà mai matchata!
     */
    #[Get('search')]
    public function search(): array
    {
        $query = $_GET['q'] ?? '';

        return [
            'success' => true,
            'query' => $query,
            'data' => [
                ['id' => 1, 'title' => "Risultato per: {$query}"],
            ]
        ];
    }

    /**
     * GET /posts/:id
     * Recupera un singolo post
     */
    #[Get(':id')]
    public function findOne(string $id): array
    {
        // Simula il recupero dal database
        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'title' => "Post #{$id}",
                'body' => 'Contenuto del post...',
                'author' => 'Mario Rossi',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * GET /posts/:id/detail
     * Recupera dettagli completi del post
     */
    #[Get(':id/detail')]
    public function getDetail(string $id): array
    {
        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'title' => "Post #{$id} - Dettaglio Completo",
                'body' => 'Contenuto completo del post...',
                'author' => [
                    'id' => 1,
                    'name' => 'Mario Rossi',
                    'email' => 'mario@example.com'
                ],
                'tags' => ['php', 'framework', 'sophia'],
                'comments' => [
                    ['id' => 1, 'text' => 'Ottimo post!'],
                    ['id' => 2, 'text' => 'Molto interessante']
                ],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * POST /posts
     * Crea un nuovo post
     */
    #[Post()]
    public function create(): array
    {
        // Recupera i dati dalla richiesta
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Simula la creazione nel database
        $newId = rand(100, 999);

        return [
            'success' => true,
            'message' => 'Post creato con successo',
            'data' => [
                'id' => $newId,
                'title' => $data['title'] ?? 'Untitled',
                'body' => $data['body'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * PUT /posts/:id
     * Aggiorna un post esistente
     */
    #[Put(':id')]
    public function update(string $id): array
    {
        // Recupera i dati dalla richiesta
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        return [
            'success' => true,
            'message' => "Post #{$id} aggiornato con successo",
            'data' => [
                'id' => $id,
                'title' => $data['title'] ?? 'Updated',
                'body' => $data['body'] ?? '',
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * DELETE /posts/:id
     * Elimina un post
     */
    #[Delete(':id')]
    public function remove(string $id): array
    {
        return [
            'success' => true,
            'message' => "Post #{$id} eliminato con successo"
        ];
    }
}