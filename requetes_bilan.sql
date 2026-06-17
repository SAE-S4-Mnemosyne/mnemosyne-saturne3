-- Requetes SQL du bilan (par annee, basees sur Decision_Annuelle)

-- 1. Profil annuel par annee scolaire
SELECT
    da.annee_scolaire,
    COUNT(*) AS total,
    SUM(CASE WHEN da.decision IN ('ADM','ADSUP','PASD','PAS1NCI','ADJ') THEN 1 ELSE 0 END) AS nb_admis,
    SUM(CASE WHEN da.decision IN ('AJ', 'NAR','RED') THEN 1 ELSE 0 END) AS nb_ajournes,
    SUM(CASE WHEN da.decision IN ('DEM','ABAN','ABL','DEF') THEN 1 ELSE 0 END) AS nb_abandons,
    ROUND(100 * SUM(CASE WHEN da.decision IN ('ADM','ADSUP','PASD','PAS1NCI','ADJ') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 1) AS pct_admis,
    ROUND(100 * SUM(CASE WHEN da.decision IN ('AJ', 'NAR','RED') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 1) AS pct_ajournes,
    ROUND(100 * SUM(CASE WHEN da.decision IN ('DEM','ABAN','ABL','DEF') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 1) AS pct_abandons
FROM Decision_Annuelle da
GROUP BY da.annee_scolaire
ORDER BY da.annee_scolaire;

-- 2. Profil annuel par formation et par annee
SELECT
    formation,
    annee_scolaire,
    COUNT(*) AS total,
    SUM(CASE WHEN decision IN ('ADM','ADSUP','PASD','PAS1NCI','ADJ') THEN 1 ELSE 0 END) AS nb_admis,
    SUM(CASE WHEN decision IN ('AJ', 'NAR','RED') THEN 1 ELSE 0 END) AS nb_ajournes,
    SUM(CASE WHEN decision IN ('DEM','ABAN','ABL','DEF') THEN 1 ELSE 0 END) AS nb_abandons
FROM (
    SELECT
        da.code_nip,
        da.annee_scolaire,
        da.decision,
        MIN(f.titre) AS formation
    FROM Decision_Annuelle da
    JOIN Inscription i ON i.code_nip = da.code_nip
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
                              AND LEFT(si.annee_scolaire, 4) = LEFT(da.annee_scolaire, 4)
    JOIN Formation f ON si.id_formation = f.id_formation
    GROUP BY da.code_nip, da.annee_scolaire, da.decision
) t
GROUP BY formation, annee_scolaire
ORDER BY formation, annee_scolaire;

-- 3. Taux d'abandon par annee : code d'abandon OU absent l'annee suivante (hors diplomes)
SELECT
    pa.annee,
    COUNT(*) AS effectif,
    SUM(CASE WHEN pa.abandon_code = 1
              OR (pa.annee < mx.max_annee AND pn.code_nip IS NULL AND dip.code_nip IS NULL)
             THEN 1 ELSE 0 END) AS nb_abandons,
    ROUND(100 * SUM(CASE WHEN pa.abandon_code = 1
              OR (pa.annee < mx.max_annee AND pn.code_nip IS NULL AND dip.code_nip IS NULL)
             THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 1) AS taux_abandon
FROM (
    SELECT code_nip,
           CAST(LEFT(annee_scolaire, 4) AS UNSIGNED) AS annee,
           MAX(CASE WHEN decision IN ('DEM','ABAN','ABL','DEF') THEN 1 ELSE 0 END) AS abandon_code
    FROM Decision_Annuelle
    GROUP BY code_nip, CAST(LEFT(annee_scolaire, 4) AS UNSIGNED)
) pa
CROSS JOIN (SELECT MAX(CAST(LEFT(annee_scolaire, 4) AS UNSIGNED)) AS max_annee FROM Decision_Annuelle) mx
LEFT JOIN (
    SELECT DISTINCT code_nip, CAST(LEFT(annee_scolaire, 4) AS UNSIGNED) AS annee
    FROM Decision_Annuelle
) pn ON pn.code_nip = pa.code_nip AND pn.annee = pa.annee + 1
LEFT JOIN (
    SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    WHERE si.numero_semestre IN (5,6) AND i.decision_jury IN ('ADM','ADSUP','CMP','ADJ')
) dip ON dip.code_nip = pa.code_nip AND dip.annee = pa.annee
GROUP BY pa.annee
ORDER BY pa.annee;

-- 4. Taux de reorientation : changement de formation
SELECT
    COUNT(*) AS nb_etudiants,
    SUM(reoriente) AS nb_reorientes,
    ROUND(100 * SUM(reoriente) / NULLIF(COUNT(*),0), 1) AS taux_reorientation
FROM (
    SELECT
        i.code_nip,
        CASE WHEN COUNT(DISTINCT si.id_formation) > 1 THEN 1 ELSE 0 END AS reoriente
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    GROUP BY i.code_nip
) t;

-- 5. Effectifs par formation et par annee
SELECT
    f.titre AS formation,
    si.annee_scolaire,
    COUNT(DISTINCT i.code_nip) AS nb_etudiants
FROM Inscription i
JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
JOIN Formation f ON si.id_formation = f.id_formation
GROUP BY f.id_formation, f.titre, si.annee_scolaire
ORDER BY f.titre, si.annee_scolaire;

-- 6. Admis : repartition validees/total par formation et par annee (ex: 4/5, 5/6)
SELECT
    formation,
    annee_scolaire,
    CONCAT(nb_validees, '/', nb_total) AS ratio,
    COUNT(*) AS nb_etudiants,
    ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (PARTITION BY formation, annee_scolaire), 1) AS pct
FROM (
    SELECT
        i.id_inscription,
        f.titre AS formation,
        si.annee_scolaire,
        COUNT(*) AS nb_total,
        SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP','ADJ') THEN 1 ELSE 0 END) AS nb_validees
    FROM Inscription i
    JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    JOIN Formation f ON si.id_formation = f.id_formation
    JOIN Decision_Annuelle da ON da.code_nip = i.code_nip
                              AND LEFT(da.annee_scolaire, 4) = LEFT(si.annee_scolaire, 4)
                              AND da.decision IN ('ADM','ADSUP','PASD','PAS1NCI','ADJ')
    GROUP BY i.id_inscription, f.titre, si.annee_scolaire
) t
GROUP BY formation, annee_scolaire, nb_total, nb_validees
ORDER BY formation, annee_scolaire, nb_total, nb_validees;
