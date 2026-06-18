/* 
Proportion admis / ajournés par semestre
*/
SELECT
    s.numero_semestre,
    COUNT(*) AS total_decisions,


    SUM(CASE WHEN i.decision_jury IN ('ADM', 'ADSUP', 'PASD', 'PAS1NCI') THEN 1 ELSE 0 END) AS nb_admis,

    SUM(CASE WHEN i.decision_jury IN ('ADJ', 'NAR', 'RED', 'DEF', 'ATJ', 'RAT') THEN 1 ELSE 0 END) AS nb_ajournes,

    ROUND(
        100 * SUM(CASE WHEN i.decision_jury IN ('ADM', 'ADSUP', 'PASD', 'PAS1NCI') THEN 1 ELSE 0 END)
        / NULLIF(COUNT(*), 0),
        1
    ) AS pct_admis,

    ROUND(
        100 * SUM(CASE WHEN i.decision_jury IN ('ADJ', 'NAR', 'RED', 'DEF', 'ATJ', 'RAT') THEN 1 ELSE 0 END)
        / NULLIF(COUNT(*), 0),
        1
    ) AS pct_ajournes

FROM Inscription i
JOIN Semestre_Instance s
    ON i.id_formsemestre = s.id_formsemestre
WHERE i.decision_jury IS NOT NULL
  AND s.numero_semestre BETWEEN 1 AND 6
GROUP BY s.numero_semestre
ORDER BY s.numero_semestre;

/* nb reorientation */
 SELECT
    COUNT(*) AS total_etudiants,
    SUM(est_reoriente) AS total_reorientes,
    ROUND(SUM(est_reoriente) * 100.0 / NULLIF(COUNT(*), 0), 1) AS taux_reorientation
FROM (
    SELECT
        i.code_nip,
        CASE
            WHEN COUNT(DISTINCT s.id_Formation) > 1
              OR (
                    SUM(s.numero_semestre IN (1, 2)) = 0
                AND SUM(s.numero_semestre IN (3, 4, 5, 6)) > 0
              )
            THEN 1
            ELSE 0
        END AS est_reoriente
    FROM Inscription i
    INNER JOIN Semestre_Instance s
        ON s.id_formsemestre = i.id_formsemestre
    GROUP BY i.code_nip
) parcours_etudiant;
/* nb reorientation par an 2021 2022 2023 2024*/
SELECT
    parcours.annee,
    COUNT(*) AS nb_etudiants,
    SUM(parcours.est_reoriente) AS nb_reorientes,
    ROUND(
        SUM(parcours.est_reoriente) * 100.0 / NULLIF(COUNT(*), 0),
        1
    ) AS taux_reorientation
FROM (
    SELECT
        i.code_nip,
        MIN(s.annee_scolaire) AS annee,
        CASE
            WHEN COUNT(DISTINCT s.id_Formation) > 1
              OR (
                    SUM(s.numero_semestre IN (1, 2)) = 0
                AND SUM(s.numero_semestre IN (3, 4, 5, 6)) > 0
              )
            THEN 1
            ELSE 0
        END AS est_reoriente
    FROM Inscription i
    JOIN Semestre_Instance s
        ON s.id_formsemestre = i.id_formsemestre
    GROUP BY i.code_nip
) parcours
GROUP BY parcours.annee
ORDER BY parcours.annee;

/* nb abandon */
SELECT
    annee_actuelle.annee,
    COUNT(*) AS effectif,
    SUM(
        CASE
            WHEN annee_suivante.code_nip IS NULL
             AND fin_cycle.code_nip IS NULL
            THEN 1
            ELSE 0
        END
    ) AS nb_abandons,
    ROUND(
        SUM(
            CASE
                WHEN annee_suivante.code_nip IS NULL
                 AND fin_cycle.code_nip IS NULL
                THEN 1
                ELSE 0
            END
        ) * 100.0 / NULLIF(COUNT(*), 0),
        1
    ) AS taux_abandon
FROM (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM Inscription i
    INNER JOIN Semestre_Instance s
        ON s.id_formsemestre = i.id_formsemestre
) annee_actuelle
LEFT JOIN (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM Inscription i
    INNER JOIN Semestre_Instance s
        ON s.id_formsemestre = i.id_formsemestre
) annee_suivante
    ON annee_suivante.code_nip = annee_actuelle.code_nip
   AND annee_suivante.annee = annee_actuelle.annee + 1
LEFT JOIN (
    SELECT DISTINCT
        i.code_nip,
        s.annee_scolaire AS annee
    FROM Inscription i
    INNER JOIN Semestre_Instance s
        ON s.id_formsemestre = i.id_formsemestre
    WHERE s.numero_semestre IN (5, 6)
) fin_cycle
    ON fin_cycle.code_nip = annee_actuelle.code_nip
   AND fin_cycle.annee = annee_actuelle.annee
WHERE annee_actuelle.annee < (
    SELECT MAX(annee_scolaire)
    FROM Semestre_Instance
)
GROUP BY annee_actuelle.annee
ORDER BY annee_actuelle.annee;

/*Analyse des effectifs par Formation*/
SELECT
    f.titre AS Formation,
    COUNT(DISTINCT i.code_nip) AS nb_etudiants,
    COUNT(*) AS nb_inscriptions
FROM inscription i
JOIN Semestre_Instance s
    ON i.id_formsemestre = s.id_formsemestre
JOIN Formation f
    ON s.id_Formation = f.id_Formation
GROUP BY f.titre
ORDER BY nb_etudiants DESC;



/*les differentes decisions par an 2021 2022 2023 2024s*/

SELECT
    annee_scolaire,

    SUM(decision = 'ADM') AS ADM,
    SUM(decision = 'ADSUP') AS ADSUP,
    SUM(decision = 'PASD') AS PASD,
    SUM(decision = 'PAS1NCI') AS PAS1NCI,

    SUM(decision = 'NAR') AS NAR,
    SUM(decision = 'RED') AS RED,
    SUM(decision = 'ADJ') AS ADJ,
    SUM(decision = 'DEF') AS DEF,

    SUM(decision = 'ABAN') AS ABAN,
    SUM(decision = 'DEM') AS DEM,
    SUM(decision = 'ABL') AS ABL,

    SUM(decision IS NULL) AS nb_null,

    COUNT(*) AS total
FROM Decision_Annuelle
GROUP BY annee_scolaire
ORDER BY annee_scolaire;