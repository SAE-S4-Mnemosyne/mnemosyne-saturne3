/*EXPLAIN avant index*/
/* EXPLAIN AVANT INDEX - Requête admis / ajournés */
EXPLAIN
SELECT
    s.numero_semestre,
    COUNT(*) AS total_decisions,
    SUM(CASE WHEN i.decision_jury IN ('ADM', 'ADSUP', 'PASD', 'PAS1NCI') THEN 1 ELSE 0 END) AS nb_admis,
    SUM(CASE WHEN i.decision_jury IN ('ADJ', 'NAR', 'RED', 'DEF', 'ATJ', 'RAT') THEN 1 ELSE 0 END) AS nb_ajournes
FROM inscription i
JOIN semestre_instance s
    ON i.id_formsemestre = s.id_formsemestre
WHERE i.decision_jury IS NOT NULL
  AND s.numero_semestre BETWEEN 1 AND 6
GROUP BY s.numero_semestre;


/* EXPLAIN AVANT INDEX - Requête réorientation */
EXPLAIN
SELECT
    COUNT(*) AS total_etudiants,
    SUM(est_reoriente) AS total_reorientes
FROM (
    SELECT
        i.code_nip,
        CASE
            WHEN COUNT(DISTINCT s.id_formation) > 1
              OR (
                    SUM(s.numero_semestre IN (1, 2)) = 0
                AND SUM(s.numero_semestre IN (3, 4, 5, 6)) > 0
              )
            THEN 1
            ELSE 0
        END AS est_reoriente
    FROM inscription i
    INNER JOIN semestre_instance s
        ON s.id_formsemestre = i.id_formsemestre
    GROUP BY i.code_nip
) parcours_etudiant;


/* EXPLAIN AVANT INDEX - Requête abandon */
EXPLAIN
SELECT
    annee_actuelle.annee,
    COUNT(*) AS effectif
FROM (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM inscription i
    INNER JOIN semestre_instance s
        ON s.id_formsemestre = i.id_formsemestre
) annee_actuelle
LEFT JOIN (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM inscription i
    INNER JOIN semestre_instance s
        ON s.id_formsemestre = i.id_formsemestre
) annee_suivante
    ON annee_suivante.code_nip = annee_actuelle.code_nip
   AND annee_suivante.annee = annee_actuelle.annee + 1
LEFT JOIN (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM inscription i
    INNER JOIN semestre_instance s
        ON s.id_formsemestre = i.id_formsemestre
    WHERE s.numero_semestre IN (5, 6)
) fin_cycle
    ON fin_cycle.code_nip = annee_actuelle.code_nip
   AND fin_cycle.annee = annee_actuelle.annee
WHERE annee_actuelle.annee < (
    SELECT MAX(annee_scolaire)
    FROM semestre_instance
)
GROUP BY annee_actuelle.annee;


/* EXPLAIN AVANT INDEX - Requête effectifs par formation */
EXPLAIN
SELECT
    f.titre AS formation,
    COUNT(DISTINCT i.code_nip) AS nb_etudiants,
    COUNT(*) AS nb_inscriptions
FROM inscription i
JOIN semestre_instance s
    ON i.id_formsemestre = s.id_formsemestre
JOIN formation f
    ON s.id_formation = f.id_formation
GROUP BY f.titre
ORDER BY nb_etudiants DESC;

/*creation d'index pour optimiser les requêtes*/
CREATE INDEX idx_inscription_decision_jury
ON inscription(decision_jury);

CREATE INDEX idx_inscription_formsemestre
ON inscription(id_formsemestre);

CREATE INDEX idx_inscription_code_nip
ON inscription(code_nip);

CREATE INDEX idx_semestre_annee
ON semestre_instance(annee_scolaire);

CREATE INDEX idx_semestre_numero
ON semestre_instance(numero_semestre);

CREATE INDEX idx_semestre_formation
ON semestre_instance(id_formation);

CREATE INDEX idx_formation_titre
ON formation(titre);