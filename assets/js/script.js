document.addEventListener("DOMContentLoaded", () => {
        // Sélectionner tous les titres h2 et leurs contenus associés
        const titres = document.querySelectorAll("form>h2, form>h3");

        titres.forEach((titre) => {
          // Créer un élément pour le contenu qui suit
          // Créer un conteneur pour le contenu
          let contenu = [];
          let sibling = titre.nextElementSibling;

          // Récupérer tous les éléments jusqu'à la prochaine balise h2
          while (sibling && !(sibling.tagName == "H2" || sibling.tagName == "H3" || (sibling.tagName == "P" && sibling.classList.contains("submit")))) {
            contenu.push(sibling);
            sibling = sibling.nextElementSibling;
          }

          // Si du contenu a été trouvé
          if (contenu.length > 0) {
            // Cacher le contenu par défaut
            contenu.forEach(function (el) {
              el.style.display = "none";
            });

            // Ajouter la classe pour le titre
            titre.classList.add("titre-section");

            // Gérer le clic sur le titre
            titre.addEventListener("click", function () {
              // Afficher ou masquer le contenu
              contenu.forEach(function (el) {
                el.style.display = el.style.display === "none" ? "" : "none";
              });
              titre.classList.toggle("active");
            });
          }
        });
    });
