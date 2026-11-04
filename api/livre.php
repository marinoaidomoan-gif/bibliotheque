<?php
/**
 * api/livres.php — API JSON publique du catalogue (lecture seule)
 *
 * GET /api/livres.php
 *   q          (texte, facultatif)  recherche sur titre, auteur, catégorie
 *   categorie  (texte, facultatif)  filtre exact sur la catégorie
 *   limit      (entier, facultatif) de 1 à 60, défaut 24
 *   offset     (entier, facultatif) pagination, défaut 0
 *   disponible (0|1, facultatif)    1 = uniquement les livres disponibles
 *
 * Réponse :
 *   {
 *     "data":       [ {id, titre, auteur, categorie, annee, stock,
 *                      disponibles, disponible, couverture_url, resume} ],
 *     "meta":       {total, limit, offset},
 *     "categories": ["Histoire", "Informatique", ...]
 *   }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// En-têtes envoyés avec chaque réponse
header('Cache-Control: no-store');            // le navigateur ne garde pas de copie (données toujours fraîches)
header('X-Content-Type-Options: nosniff');    // le navigateur ne "devine" pas le type du contenu

// Cette API ne sert qu'à LIRE : toute autre méthode (POST, DELETE…) est refusée
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Méthode non autorisée.'], 405);
}

// ---------------------------------------------------------------------------
// 1) Lecture et validation des paramètres de l'URL
// ---------------------------------------------------------------------------
$q          = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);          // 100 caractères maximum
$categorie  = mb_substr(trim((string) ($_GET['categorie'] ?? '')), 0, 100);
$disponible = isset($_GET['disponible']) && $_GET['disponible'] === '1';

// filter_var vérifie que c'est bien un entier dans l'intervalle, sinon prend la valeur par défaut
$limit  = filter_var($_GET['limit'] ?? 24, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 60, 'default' => 24],
]);
$offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'default' => 0],
]);

// Si l'utilisateur tape % ou _ dans la recherche, on les "neutralise"
// (sinon ils agiraient comme des jokers dans le LIKE)
$likeTerm = '%' . addcslashes($q, '%_\\') . '%';

try {
    $pdo = db();

    // -----------------------------------------------------------------------
    // 2) Construction de la clause WHERE
    //    Les valeurs ne sont JAMAIS collées dans le SQL : on utilise des
    //    placeholders (:q1, :categorie…) → protection contre l'injection SQL.
    // -----------------------------------------------------------------------
    $where  = [];
    $params = [];

    if ($q !== '') {
        // Trois placeholders différents : PDO n'accepte pas le même nom deux fois
        $where[] = '(l.titre LIKE :q1 OR l.auteur LIKE :q2 OR l.categorie LIKE :q3)';
        $params[':q1'] = $likeTerm;
        $params[':q2'] = $likeTerm;
        $params[':q3'] = $likeTerm;
    }

    if ($categorie !== '') {
        $where[] = 'l.categorie = :categorie';
        $params[':categorie'] = $categorie;
    }

    if ($disponible) {
        // "disponibles" est calculé par la vue v_livres : stock - emprunts non rendus
        $where[] = 'l.disponibles > 0';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // -----------------------------------------------------------------------
    // 3) Nombre total de résultats (sert à la pagination)
    // -----------------------------------------------------------------------
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM v_livres l $whereSql");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    // -----------------------------------------------------------------------
    // 4) Les livres de la page demandée
    // -----------------------------------------------------------------------
    $sql = "
        SELECT l.id, l.titre, l.auteur, l.categorie, l.annee,
               l.stock, l.couverture, l.resume, l.disponibles
        FROM v_livres l
        $whereSql
        ORDER BY l.titre ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $cle => $valeur) {
        $stmt->bindValue($cle, $valeur, PDO::PARAM_STR);
    }
    // LIMIT et OFFSET doivent être liés comme des ENTIERS
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    // -----------------------------------------------------------------------
    // 5) Mise en forme : on renvoie des types propres (int, bool, null)
    // -----------------------------------------------------------------------
    $livres = array_map(static function (array $row): array {
        $disponibles = max(0, (int) $row['disponibles']);   // jamais négatif

        // URL de la couverture (basename() empêche toute tentative de "../")
        $couverture = ($row['couverture'] !== null && $row['couverture'] !== '')
            ? 'assets/uploads/couvertures/' . rawurlencode(basename((string) $row['couverture']))
            : null;

        return [
            'id'             => (int) $row['id'],
            'titre'          => $row['titre'],
            'auteur'         => $row['auteur'],
            'categorie'      => $row['categorie'],
            'annee'          => $row['annee'] !== null ? (int) $row['annee'] : null,
            'stock'          => (int) $row['stock'],
            'disponibles'    => $disponibles,
            'disponible'     => $disponibles > 0,
            'couverture_url' => $couverture,
            'resume'         => $row['resume'],
        ];
    }, $stmt->fetchAll());

    // Liste des catégories (pour les "pills" du catalogue)
    $categories = $pdo->query(
        'SELECT DISTINCT categorie FROM livres ORDER BY categorie ASC'
    )->fetchAll(PDO::FETCH_COLUMN);

    json_response([
        'data'       => $livres,
        'meta'       => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        'categories' => array_values($categories),
    ]);
} catch (Throwable $e) {
    // Le détail va dans le journal d'erreurs ; l'utilisateur ne voit qu'un message simple
    error_log('[api/livres] ' . $e->getMessage());

    $reponse = ['error' => 'Impossible de charger le catalogue.'];
    if (APP_DEBUG) {
        $reponse['debug'] = $e->getMessage();   // visible seulement en développement
    }
    json_response($reponse, 500);
}