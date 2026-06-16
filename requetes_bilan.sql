-- Requetes SQL du bilan

-- 1. Admis vs ajournes par semestre
SELECT
    si.numero_semestre,
    COUNT(*) AS total_decisions,
    SUM(CASE WHEN i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ') THEN 1 ELSE 0 END) AS nb_admis,
    SUM(CASE WHEN i.decision_jury IN ('RED','AJ','ATJ') THEN 1 ELSE 0 END) AS nb_ajournes,
    ROUND(100 * SUM(CASE WHEN i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 1) AS pct_admis,
    ROUND(100 * SUM(CASE WHEN i.decision_jury IN ('RED','AJ','ATJ') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 1) AS pct_ajournes
FROM Inscription i
JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
WHERE i.decision_jury IS NOT NULL
GROUP BY si.numero_semestre
ORDER BY si.numero_semestre;

-- 2. Taux d'abandon : present une annee, absent l'annee suivante, hors diplomes
SELECT
    pa.annee,
    COUNT(*) AS effectif,
    SUM(CASE WHEN pn.code_nip IS NULL AND dip.code_nip IS NULL THEN 1 ELSE 0 END) AS nb_abandons,
    ROUND(100 * SUM(CASE WHEN pn.code_nip IS NULL AND dip.code_nip IS NULL THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 1) AS taux_abandon
FROM (
    SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
) pa
LEFT JOIN (
    SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
) pn ON pn.code_nip = pa.code_nip AND pn.annee = pa.annee + 1
LEFT JOIN (
    SELECT DISTINCT i.code_nip, CAST(LEFT(si.annee_scolaire, 4) AS UNSIGNED) AS annee
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    WHERE si.numero_semestre IN (5,6) AND i.decision_jury IN ('ADM','ADSUP','CMP','ADJ')
) dip ON dip.code_nip = pa.code_nip AND dip.annee = pa.annee
WHERE pa.annee < (SELECT MAX(CAST(LEFT(annee_scolaire, 4) AS UNSIGNED)) FROM Semestre_Instance)
GROUP BY pa.annee
ORDER BY pa.annee;

-- 3. Taux de reorientation : changement de formation OU entree directe en 2e/3e annee
SELECT
    COUNT(*) AS nb_etudiants,
    SUM(reoriente) AS nb_reorientes,
    ROUND(100 * SUM(reoriente) / NULLIF(COUNT(*), 0), 1) AS taux_reorientation
FROM (
    SELECT
        i.code_nip,
        CASE WHEN COUNT(DISTINCT si.id_formation) > 1
                  OR (SUM(CASE WHEN si.numero_semestre IN (1,2) THEN 1 ELSE 0 END) = 0
                      AND SUM(CASE WHEN si.numero_semestre IN (3,4,5,6) THEN 1 ELSE 0 END) > 0)
             THEN 1 ELSE 0 END AS reoriente
    FROM Inscription i
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    GROUP BY i.code_nip
) t;

-- 4. Effectifs par formation
SELECT
    f.titre AS formation,
    COUNT(DISTINCT i.code_nip) AS nb_etudiants,
    COUNT(*) AS nb_inscriptions
FROM Inscription i
JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
JOIN Formation f ON si.id_formation = f.id_formation
GROUP BY f.id_formation, f.titre
ORDER BY nb_etudiants DESC;

-- 5. Repartition des admis par competences validees (ex: 4/6, 5/6, 6/6)
SELECT
    nb_total AS competences_total,
    nb_validees AS competences_validees,
    COUNT(*) AS nb_etudiants,
    ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (), 1) AS pct_etudiants
FROM (
    SELECT
        i.id_inscription,
        COUNT(*) AS nb_total,
        SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP') THEN 1 ELSE 0 END) AS nb_validees
    FROM Inscription i
    JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
    WHERE i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ')
    GROUP BY i.id_inscription
) t
GROUP BY nb_total, nb_validees
ORDER BY nb_total, nb_validees;

-- 6. Meme repartition, declinee par formation
SELECT
    formation,
    nb_total AS competences_total,
    nb_validees AS competences_validees,
    COUNT(*) AS nb_etudiants,
    ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (PARTITION BY formation), 1) AS pct_formation
FROM (
    SELECT
        i.id_inscription,
        f.titre AS formation,
        COUNT(*) AS nb_total,
        SUM(CASE WHEN rc.code_decision IN ('ADM','ADSUP','CMP') THEN 1 ELSE 0 END) AS nb_validees
    FROM Inscription i
    JOIN Resultat_Competence rc ON rc.id_inscription = i.id_inscription
    JOIN Semestre_Instance si ON i.id_formsemestre = si.id_formsemestre
    JOIN Formation f ON si.id_formation = f.id_formation
    WHERE i.decision_jury IN ('ADM','ADSUP','PASD','CMP','ADJ')
    GROUP BY i.id_inscription, f.titre
) t
GROUP BY formation, nb_total, nb_validees
ORDER BY formation, nb_total, nb_validees;
