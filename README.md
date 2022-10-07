# plg_task_moisdudoc_j4
 
Plugin Task Mois du Doc : mise à jour des custom fields des séances.

Ce plugin, visible dans les tâches planifiées, met à jour les champs personnalisés Réalisateur/Date/Quand des séances, ainsi que la catégorie d'une séance.

Attention : l'ordre des custom fields est important, car le plugin les lit dans l'ordre de leurs ids: 
- le réalisateur (champ 28) est trouvé à partir du no d'article de la fiche d'un documentaire (champ 13)
- les champs quand (27) et date (29) sont mis à jour à partir de la date/heure de séance (champ 12). 