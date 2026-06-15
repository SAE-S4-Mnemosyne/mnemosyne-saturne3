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

FROM inscription i
JOIN semestre_instance s
    ON i.id_formsemestre = s.id_formsemestre
WHERE i.decision_jury IS NOT NULL
  AND s.numero_semestre BETWEEN 1 AND 6
GROUP BY s.numero_semestre
ORDER BY s.numero_semestre;