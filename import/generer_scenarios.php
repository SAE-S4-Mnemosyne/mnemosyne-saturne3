/* 
Génération automatique des scénarios de correspondance entre formations.

Cette table permet d'identifier les différents types de flux possibles
(passage, redoublement, réorientation) observés dans les parcours étudiants.

Elle a été conservée pour préparer d'éventuelles visualisations de flux,
même si les indicateurs du bilan sont aujourd'hui calculés directement à
partir des tables inscription et semestre_instance.
*/

INSERT IGNORE INTO scenario_correspondance
(id_formation_source, id_formation_cible, type_flux)
SELECT DISTINCT
    s1.id_formation AS id_formation_source,
    s2.id_formation AS id_formation_cible,
    CASE
        WHEN s1.id_formation <> s2.id_formation THEN 'reorientation'
        WHEN s2.numero_semestre = s1.numero_semestre THEN 'redoublement'
        WHEN s2.numero_semestre > s1.numero_semestre THEN 'passage'
        ELSE 'autre'
    END AS type_flux
FROM inscription i1
JOIN inscription i2
    ON i1.code_nip = i2.code_nip
JOIN semestre_instance s1
    ON i1.id_formsemestre = s1.id_formsemestre
JOIN semestre_instance s2
    ON i2.id_formsemestre = s2.id_formsemestre
WHERE s1.id_formsemestre <> s2.id_formsemestre
  AND s1.annee_scolaire < s2.annee_scolaire
  AND s1.id_formation IS NOT NULL
  AND s2.id_formation IS NOT NULL;