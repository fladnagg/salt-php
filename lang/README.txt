
-- TODO a implementer... aucune solution propre existante :
  * gettext
    - ne gere pas nativement les templates
    - necessite l'utilisation d'outil externe pour la modification des fichiers de localisation (.po / .mo)
    - conseille l'utilisation de texte en dur comme clé, ce qui ne parait pas correct :
      * Impossible de faire la distinction entre des clés différentes si elles correspondent au même texte dans une langue
      * Changement du texte d'origine implique la modification de toute les fichiers de traduction... 
  * intl 
    - format complexe de fichier de traduction (.dat)
    - beaucoup trop compliqué pour le besoin
    
  => Implémenter un simple parseur yaml avec un translator a la Ruby.

  
Une implémentation interessante : https://github.com/Philipp15b/php-i18n
Avantages :
	* API propre et courte ET avec nom configurable
	* Gestion des placeholders avec formatage

Inconvénients :
	* Compilation a la volée de classe (bof) : En php 5.3+ c'est mieux d'utiliser class_alias()
	* chargement de TOUTES les données en constantes... (mais une fois que ca a été lu... pourquoi pas...)
	* Depend d'un parseur externe pour YAML : https://github.com/mustangostang/spyc
	* pas de cache des propriétés, mais est-ce vraiment nécessaire ? Une fois la donnée chargée
	* ini/properties/json (inutile)
