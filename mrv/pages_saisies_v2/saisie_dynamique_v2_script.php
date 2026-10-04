<script>
(async () => {
    // --- 1. Variables de Contexte (PHP -> JS) ---
    const SECTEUR_ID = <?php echo $secteur_id; ?>;
    const ANNEE_ID = <?php echo $annee_id; ?>;
    const IS_COORDONNATEUR = <?php echo json_encode($is_coordonnateur); ?>;
    
    // ==================================================================
    // --- Permissions ---
    // ==================================================================
    const CAN_DELETE = <?php echo json_encode($can_delete); ?>;
    const CAN_VALIDATE = <?php echo json_encode($can_validate); ?>;
    const CAN_EDIT = <?php echo json_encode($can_edit); ?>; 
    
    const HAS_SOUS_CATEGORIE = (SECTEUR_ID === 3) ? false : <?php echo json_encode($has_sous_categorie); ?>;
    const HAS_COMBUSTIBLE = <?php echo json_encode($has_combustible); ?>;
    
    // API
    const SaisieAPI = `/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=${SECTEUR_ID}`;
    
    // --- 2. Références DOM ---
    const statusMessage = document.getElementById('statusMessageSaisie');
    
    // Nouveaux champs d'Identification et DA
    const fournisseurSelect = document.getElementById('fournisseurSelect'); 
    const personneContact = document.getElementById('personneContact');
    const categorieSelect = document.getElementById('categorieSelect');
    const sousCategorieSelect = document.getElementById('sousCategorieSelect');
    const sourceEmissionSelect = document.getElementById('sourceEmissionSelect');
    const typeCombustibleSelect = document.getElementById('typeCombustibleSelect'); 
    const disponibiliteDa = document.getElementById('disponibiliteDa');
    const valeurSaisie1 = document.getElementById('valeurSaisie1'); // Description DA
    const valeurSaisie2 = document.getElementById('valeurSaisie2'); // Valeur DA
    const uniteMesureSelect = document.getElementById('uniteMesureSelect'); // Unité DA
    const incertitudeDa = document.getElementById('incertitudeDa');
    const anneeProductionDa = document.getElementById('anneeProductionDa'); // NOUVEAU CHAMP
    
    // Nouveaux champs FE et GES
    const gesSelect = document.getElementById('gesSelect');
    const valeurFe = document.getElementById('valeurFe');
    const uniteFe = document.getElementById('uniteFe');
    const incertitudeFe = document.getElementById('incertitudeFe');

    // Commentaires et Validation
    const commentaireFournisseur = document.getElementById('commentaireFournisseur');
    const commentaireValidateur = document.getElementById('commentaireValidateur');
    const validationCheckbox = document.getElementById('validationCheckbox');
    
    const saisieForm = document.getElementById('saisieForm');
    const saisieFormTitle = document.getElementById('saisieFormTitle');
    const saisieIdField = document.getElementById('saisieId');
    const submitSaisieForm = document.getElementById('submitSaisieForm');
    const clearSaisieFormBtn = document.getElementById('clearSaisieForm');
    const saisiesTableBody = document.getElementById('saisiesTableBody');
    const checkAllSaisies = document.getElementById('checkAllSaisies');
    const validateSelected = document.getElementById('validateSelected');
    const invalidateSelected = document.getElementById('invalidateSelected');
    
    const filterFournisseurSelect = document.getElementById('filterFournisseurSelect');
    const filterCategorieSelect = document.getElementById('filterCategorieSelect');
    const filterSousCategorieSelect = document.getElementById('filterSousCategorieSelect');
    const filterUtilisateurSelect = document.getElementById('filterUtilisateurSelect');
    
    const categorieLoader = document.getElementById('categorieLoader');
    const sousCategorieLoader = document.getElementById('sousCategorieLoader');
    const sourceEmissionLoader = document.getElementById('sourceEmissionLoader');
    
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalMessage = document.getElementById('deleteModalMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const closeDeleteModalBtn = document.getElementById('closeDeleteModalBtn');
    
    const sousCategorieWrapper = document.getElementById('sousCategorieWrapper'); 
    
    let currentSaisieIdToDelete = null; 
    let currentFournisseurId = <?php echo $is_coordonnateur ? 0 : $user_organisation_id; ?>; 
    let statusMessageTimer = null; 

    // --- 3. Fonctions Utilitaires ---

    const enableForm = (enable) => {
        const final_enable = enable && CAN_EDIT;
        
        // Activer/Désactiver les champs de base et DA
        valeurSaisie1.disabled = !final_enable;
        valeurSaisie2.disabled = !final_enable;
        if(disponibiliteDa) disponibiliteDa.disabled = !final_enable;
        if(incertitudeDa) incertitudeDa.disabled = !final_enable;
        if(anneeProductionDa) anneeProductionDa.disabled = !final_enable; 
        if(personneContact) personneContact.disabled = !final_enable;
        
        // Activer/Désactiver les champs FE
        if(gesSelect) gesSelect.disabled = !final_enable;
        if(valeurFe) valeurFe.disabled = !final_enable;
        if(uniteFe) uniteFe.disabled = !final_enable;
        if(incertitudeFe) incertitudeFe.disabled = !final_enable;

        commentaireFournisseur.disabled = !final_enable;
        if(commentaireValidateur) commentaireValidateur.disabled = !CAN_VALIDATE; 
        if(validationCheckbox) validationCheckbox.disabled = !CAN_VALIDATE;
        submitSaisieForm.disabled = !final_enable;
        
        categorieSelect.disabled = !final_enable;
        if(sousCategorieSelect) sousCategorieSelect.disabled = !final_enable;
        sourceEmissionSelect.disabled = !final_enable;
        
        uniteMesureSelect.disabled = !final_enable; 
        if(typeCombustibleSelect) typeCombustibleSelect.disabled = !final_enable;
        
        const fournisseurWrapper = document.getElementById('fournisseurWrapper');
        if (fournisseurWrapper) fournisseurWrapper.style.display = 'flex'; 
        if (fournisseurSelect) fournisseurSelect.disabled = false; 

        const filterFournisseurWrapper = document.querySelector('.saisie-filter-group label[for="filterFournisseurSelect"]');
        if (filterFournisseurWrapper) filterFournisseurWrapper.parentElement.style.display = 'flex'; 
        if (filterFournisseurSelect) filterFournisseurSelect.disabled = false; 
    };

    const showStatusMessage = (message, isError = false) => {
        if (!statusMessage) return;
        if (statusMessageTimer) {
            clearTimeout(statusMessageTimer);
            statusMessageTimer = null;
        }
        statusMessage.textContent = message;
        statusMessage.className = `p-3 mb-4 rounded-md ${isError ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`;
        if (!isError) {
            statusMessageTimer = setTimeout(() => {
                statusMessage.textContent = '';
                statusMessage.className = 'mb-4'; 
            }, 2000); 
        }
    };

    const resetSelect = (selectElement, defaultOptionText, defaultValue = "0") => {
        if (!selectElement) return;
        selectElement.innerHTML = `<option value="${defaultValue}">${defaultOptionText}</option>`;
    };

    const populateSelect = (selectElement, data, defaultOptionText) => {
        if (!selectElement) return;
        resetSelect(selectElement, defaultOptionText);
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.ID;
            option.textContent = item.Libelle || item.Nom_Prenom; 
            if (item.Code) option.dataset.code = item.Code;
            selectElement.appendChild(option);
        });
    };
    
    const toggleLoader = (loaderElement, show) => {
        if (loaderElement) loaderElement.style.display = show ? 'inline-block' : 'none';
    };

    const showDeleteModal = (id) => {
        currentSaisieIdToDelete = id;
        deleteModalMessage.textContent = `Êtes-vous sûr de vouloir supprimer la donnée #${id} ?`;
        if (deleteModal) deleteModal.style.display = 'block'; 
    };

    const hideDeleteModal = () => {
        currentSaisieIdToDelete = null;
        if (deleteModal) deleteModal.style.display = 'none';
    };
    
    const SaisieFetch = async (action, bodyParams = {}) => {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('fournisseur_id', currentFournisseurId);
        
        for (const key in bodyParams) {
            const value = bodyParams[key];
            if (Array.isArray(value)) {
                value.forEach(item => formData.append(`${key}[]`, item)); 
            } else if (value !== null) { 
                formData.append(key, value);
            }
        }

        try {
            const response = await fetch(SaisieAPI, { method: 'POST', body: formData });
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Erreur réseau: ${response.status} ${response.statusText}. Réponse: ${errorText}`);
            }
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Une erreur inconnue est survenue.');
            return data.data !== undefined ? data.data : data;
        } catch (error) {
            console.error(`Erreur SaisieFetch (Action: ${action}):`, error);
            showStatusMessage(`Erreur: ${error.message}`, true);
            return null; 
        }
    };
    
    function getSelectedCategoryCode() {
        if (categorieSelect && categorieSelect.selectedIndex !== -1) {
            const selectedOption = categorieSelect.options[categorieSelect.selectedIndex];
            const code = selectedOption.dataset.code; 
            if (code) return code;
            const match = selectedOption.textContent.match(/^(\w+) -/);
            return match ? match[1] : '';
        }
        return '';
    }

    // --- 4. Fonctions de Chargement des Données (Listes déroulantes) ---

    const loadInitialData = async () => {
        const data = await SaisieFetch('get_initial_data');
        if (data && data.fournisseurs) {
            populateSelect(fournisseurSelect, data.fournisseurs, '-- Veuillez choisir un fournisseur --');
            if(filterFournisseurSelect) populateSelect(filterFournisseurSelect, data.fournisseurs, '-- Tous les fournisseurs --');
        }
    };

    const loadCategories = async () => {
        toggleLoader(categorieLoader, true);
        const data = await SaisieFetch('get_categories');
        if (data) {
            populateSelect(categorieSelect, data, '-- Sélectionner --');
            populateSelect(filterCategorieSelect, data, '-- Toutes les catégories --');
        }
        toggleLoader(categorieLoader, false);
    };

    const loadUtilisateurs = async () => {
        if (!filterUtilisateurSelect) return;
        filterUtilisateurSelect.disabled = true;
        const data = await SaisieFetch('get_utilisateurs_by_secteur');
        if (data) {
            populateSelect(filterUtilisateurSelect, data, '-- Tous les utilisateurs --');
            if (data.length > 0) filterUtilisateurSelect.disabled = false;
        }
    };

    const loadSousCategories = async (categorieId, targetSelectElement = sousCategorieSelect) => {
        if (!categorieId || categorieId === "0" || !HAS_SOUS_CATEGORIE) {
            resetSelect(targetSelectElement, '-- Sélectionner d\'abord une catégorie --');
            return;
        }
        const loader = (targetSelectElement === sousCategorieSelect) ? sousCategorieLoader : null;
        toggleLoader(loader, true);
        const data = await SaisieFetch('get_sous_categories', { categorie_id: categorieId });
        if (data) {
            const defaultText = (targetSelectElement === sousCategorieSelect) ? '-- Sélectionner --' : '-- Toutes les sous-catégories --';
            populateSelect(targetSelectElement, data, defaultText);
        }
        toggleLoader(loader, false);
    };
    
    const loadSourcesEmission = async (categorieId, sousCategorieId) => {
        if (!categorieId || categorieId === "0") {
             resetSelect(sourceEmissionSelect, '-- Sélectionner (Catégorie/Sous-Cat.) --');
            return;
        }
        
        const categorieCode = getSelectedCategoryCode();
        const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B');
        
        if (HAS_SOUS_CATEGORIE && (!sousCategorieId || sousCategorieId === "0") && !isDechet4B) {
             resetSelect(sourceEmissionSelect, '-- Sélectionner une sous-catégorie --');
            return;
        }
        
        const finalSousCatId = isDechet4B ? 0 : sousCategorieId;

        toggleLoader(sourceEmissionLoader, true);
        const data = await SaisieFetch('get_sources_emission', { 
            categorie_id: categorieId,
            sous_categorie_id: finalSousCatId 
        });
        if (data) populateSelect(sourceEmissionSelect, data, '-- Sélectionner --');
        toggleLoader(sourceEmissionLoader, false);
    };
    
    // --- 5. Fonctions CRUD et Affichage Tableau ---

    const checkEnergieCombustible = () => {
        if (SECTEUR_ID !== 1 || !HAS_COMBUSTIBLE) return;
        
        const combustibleWrapper = document.getElementById('combustibleWrapper');
        if (!combustibleWrapper || !typeCombustibleSelect) return;
        
        const selectedOption = categorieSelect.options[categorieSelect.selectedIndex];
        const categorieCode = selectedOption ? selectedOption.dataset.code : '';

        if (categorieCode === '1B') {
            combustibleWrapper.style.display = 'none'; 
            typeCombustibleSelect.required = false; 
            typeCombustibleSelect.value = ''; 
        } else {
            combustibleWrapper.style.display = 'block'; 
            typeCombustibleSelect.required = true; 
        }
    };

    const refreshSaisiesTable = async () => {
        // Mise à jour du colspan pour inclure les nouvelles colonnes
        let colspan = 21; 
        if (!HAS_SOUS_CATEGORIE) colspan--;
        if (!HAS_COMBUSTIBLE) colspan--;

        saisiesTableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center p-4 text-gray-500">Chargement...</td></tr>`;
        
        let filter_fournisseur_id = filterFournisseurSelect ? filterFournisseurSelect.value : 0;
        const filter_categorie_id = filterCategorieSelect ? filterCategorieSelect.value : 0;
        const filter_sous_categorie_id = filterSousCategorieSelect ? filterSousCategorieSelect.value : 0;
        const filter_utilisateur_id = filterUtilisateurSelect ? filterUtilisateurSelect.value : 0;

        const saisies = await SaisieFetch('get_saisies', {
            filter_fournisseur_id: filter_fournisseur_id,
            filter_categorie_id: filter_categorie_id,
            filter_sous_categorie_id: filter_sous_categorie_id,
            filter_utilisateur_id: filter_utilisateur_id
        });
        
        if (saisies && saisies.length > 0) {
            saisiesTableBody.innerHTML = ''; 
            saisies.forEach(saisie => {
                saisiesTableBody.appendChild(createSaisieRow(saisie));
            });
        } else if (saisies) {
            let message = 'Aucune donnée enregistrée pour cette sélection.';
            saisiesTableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center p-4 text-gray-500">${message}</td></tr>`;
        } else {
             saisiesTableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center p-4 text-red-500">Erreur lors du chargement des données.</td></tr>`;
        }
        
        if(checkAllSaisies) checkAllSaisies.checked = false;
        updateBatchButtons();
    };

    const createSaisieRow = (saisie) => {
        const tr = document.createElement('tr');
        const validationIcon = saisie.Validation == 1 
            ? '<i class="fas fa-check-circle validation-icon valide" title="Validée"></i>' 
            : '<i class="fas fa-exclamation-circle validation-icon non-valide" title="Non validée"></i>';
        
        const fournisseur = saisie.FournisseurLibelle || '<i>N/A</i>'; 
        const contact = escapeHTML(saisie.Personne_Contact || '');
        const categorie = saisie.CategorieLibelle || '<i class="text-red-500">N/A</i>';
        const sousCategorie = saisie.SousCategorieLibelle || '<i>N/A</i>';
        const sourceEmission = saisie.SourceEmissionLibelle || '<i class="text-red-500">N/A</i>';
        
        const combustible = saisie.Type_Combustible || '<i>N/A</i>'; 
        
        // Nouveaux champs pour affichage
        const dispoDa = escapeHTML(saisie.Disponibilite_DA || '');
        const val1 = escapeHTML(saisie.Donnee_sur_activite || '');
        const val2 = escapeHTML(saisie.Valeur_Donnee || '');
        const unite = escapeHTML(saisie.Unite_mesure || '<i class="text-red-500">N/A</i>'); 
        const incertDa = escapeHTML(saisie.Incertitude_DA || '');
        const anneeProdDa = escapeHTML(saisie.Annee_Production_DA || ''); // NOUVEAU CHAMP
        
        const ges = escapeHTML(saisie.GES || '');
        const valFe = escapeHTML(saisie.Valeur_FE || '');
        const uniteFe = escapeHTML(saisie.Unite_FE || '');
        const incertFe = escapeHTML(saisie.Incertitude_FE || '');

        const commFournisseur = escapeHTML(saisie.Commentaire_fournisseur || '');
        const commValidateur = escapeHTML(saisie.Commentaire_validateur || '');
        
        let rowHTML = `
            <td class="col-validation"><input type="checkbox" class="saisie-checkbox" data-id="${saisie.ID}"></td>
            <td class="col-validation">${validationIcon}</td>
            
            <td>${fournisseur}</td>
            <td title="${contact}">${contact}</td>
            
            <td>${categorie}</td>
            ${HAS_SOUS_CATEGORIE ? `<td>${sousCategorie}</td>` : ''}
            <td>${sourceEmission}</td>
            ${HAS_COMBUSTIBLE ? `<td>${combustible}</td>` : ''}
            
            <td>${dispoDa}</td>
            <td class="col-valeur" title="${val1}">${val1}</td>
            <td class="col-valeur">${val2}</td>
            <td>${unite}</td>
            <td>${incertDa}</td>
            <td>${anneeProdDa}</td> 
            <td>${ges}</td>
            <td class="col-valeur">${valFe}</td>
            <td>${uniteFe}</td>
            <td>${incertFe}</td>

            <td class="col-commentaire" title="${commFournisseur}">${commFournisseur}</td>
            <td class="col-commentaire" title="${commValidateur}">${commValidateur}</td>
            <td class="col-actions">
                ${CAN_EDIT ? `
                    <button onclick="editSaisie(this)" class="saisie-btn saisie-btn-secondary" style="padding: 5px 8px; font-size: 12px;" title="Modifier">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                ` : ''}
                
                ${CAN_DELETE ? `
                <button onclick="deleteSaisie(${saisie.ID})" class="saisie-btn saisie-btn-secondary" style="padding: 5px 8px; font-size: 12px; background-color: var(--color-danger); color: white;" title="Supprimer">
                    <i class="fas fa-trash-alt"></i>
                </button>
                ` : ''}
            </td>
        `;
        tr.innerHTML = rowHTML;
        tr.dataset.saisieData = JSON.stringify(saisie);
        return tr;
    };
    
    function escapeHTML(str) {
        if (!str) return '';
        return str.toString()
                     .replace(/&/g, '&amp;')
                     .replace(/</g, '&lt;')
                     .replace(/>/g, '&gt;')
                     .replace(/"/g, '&quot;')
                     .replace(/'/g, '&#39;');
    }

    const setFormToNewMode = () => {
        saisieIdField.value = '0';
        
        // Reset DA
        if(disponibiliteDa) disponibiliteDa.value = '';
        valeurSaisie1.value = '';
        valeurSaisie2.value = '';
        if(incertitudeDa) incertitudeDa.value = '';
        if(anneeProductionDa) anneeProductionDa.value = ''; 
        if(uniteMesureSelect) uniteMesureSelect.value = ''; 
        if(personneContact) personneContact.value = '';
        
        // Reset FE
        if(gesSelect) gesSelect.value = '';
        if(valeurFe) valeurFe.value = '';
        if(uniteFe) uniteFe.value = '';
        if(incertitudeFe) incertitudeFe.value = '';

        if(typeCombustibleSelect) typeCombustibleSelect.value = '';
        commentaireFournisseur.value = '';
        if(commentaireValidateur) commentaireValidateur.value = '';
        if(validationCheckbox) validationCheckbox.checked = false;
        
        saisieFormTitle.textContent = 'Ajouter une donnée';
        submitSaisieForm.innerHTML = '<i class="fas fa-plus mr-1"></i> Ajouter';
        
        if (currentFournisseurId > 0) {
            fournisseurSelect.value = currentFournisseurId;
        }

        if(sourceEmissionSelect) {
            resetSelect(sourceEmissionSelect, '-- Sélectionner (Catégorie/Sous-Cat.) --');
            sourceEmissionSelect.disabled = true;
            
            const catId = categorieSelect.value;
            const categorieCode = getSelectedCategoryCode();
            const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B');
            
            let sousCatId = 0;
            
            if (HAS_SOUS_CATEGORIE) {
                if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'flex';
                if (sousCategorieSelect) sousCategorieSelect.required = true;
                
                if (isDechet4B) {
                    if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'none';
                    if (sousCategorieSelect) sousCategorieSelect.required = false;
                    sousCatId = 0; 
                } else {
                    sousCatId = (sousCategorieSelect) ? sousCategorieSelect.value : 0;
                }
            }
            
            const canLoad = (HAS_SOUS_CATEGORIE && catId > 0 && sousCatId > 0) || 
                             (!HAS_SOUS_CATEGORIE && catId > 0) || 
                             (isDechet4B && catId > 0); 

            if (canLoad) {
                loadSourcesEmission(catId, sousCatId);
                sourceEmissionSelect.disabled = !CAN_EDIT;
            } else {
                resetSelect(sourceEmissionSelect, '-- Sélectionner (Catégorie/Sous-Cat.) --');
                sourceEmissionSelect.disabled = true;
            }
        }

        checkEnergieCombustible();
    };
    
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        
        const selectedFournisseurId = parseInt(fournisseurSelect.value, 10) || 0;
        if (selectedFournisseurId === 0) {
            showStatusMessage("Erreur: Vous devez sélectionner un fournisseur.", true);
            return;
        }
        currentFournisseurId = selectedFournisseurId;
        
        if (categorieSelect.value === "0" || sourceEmissionSelect.value === "0" || uniteMesureSelect.value.trim() === "") {
             showStatusMessage("Veuillez remplir tous les champs obligatoires (Catégorie, Source d'Émission, Unité DA).", true);
             return;
        }
        
        const categorieCode = getSelectedCategoryCode();
        const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B');
        
        if (HAS_SOUS_CATEGORIE && !isDechet4B) {
            if (!sousCategorieSelect || sousCategorieSelect.value === "0") {
                showStatusMessage("Veuillez sélectionner une Sous-Catégorie.", true);
                return;
            }
        }

        if (SECTEUR_ID === 1 && HAS_COMBUSTIBLE) {
            if (categorieCode !== '1B' && (!typeCombustibleSelect || typeCombustibleSelect.value.trim() === '')) {
                showStatusMessage("Veuillez sélectionner un Type Combustible pour cette catégorie.", true);
                return; 
            }
        }

        const saisieId = saisieIdField.value || '0';
        const action = (saisieId === '0') ? 'add_saisie' : 'update_saisie';
        
        let finalSousCategorieValue = 0;
        if (HAS_SOUS_CATEGORIE) {
            finalSousCategorieValue = isDechet4B ? 0 : (sousCategorieSelect ? sousCategorieSelect.value : 0);
        }

        const bodyParams = {
            saisie_id: saisieId,
            categorie: categorieSelect.value,
            sous_categorie: finalSousCategorieValue, 
            source_emission: sourceEmissionSelect.value,
            type_combustible: HAS_COMBUSTIBLE ? (typeCombustibleSelect.value.trim() || null) : null,
            personne_contact: personneContact ? personneContact.value.trim() : '',
            
            // Nouvelles données DA
            disponibilite_da: disponibiliteDa ? disponibiliteDa.value.trim() : '',
            valeur_saisie_1: valeurSaisie1.value.trim(),
            valeur_saisie_2: valeurSaisie2.value.trim(),
            annee_production_da: anneeProductionDa ? anneeProductionDa.value.trim() : '', 
            unite_mesure: uniteMesureSelect.value.trim(),
            incertitude_da: incertitudeDa ? incertitudeDa.value.trim() : '',
            
            // Nouvelles données FE
            ges: gesSelect ? gesSelect.value.trim() : '',
            valeur_fe: valeurFe ? valeurFe.value.trim() : '',
            unite_fe: uniteFe ? uniteFe.value.trim() : '',
            incertitude_fe: incertitudeFe ? incertitudeFe.value.trim() : '',

            commentaire_fournisseur: commentaireFournisseur.value,
            commentaire_validateur: IS_COORDONNATEUR ? (commentaireValidateur ? commentaireValidateur.value : '') : '',
            validation: (IS_COORDONNATEUR && validationCheckbox && validationCheckbox.checked) ? 1 : 0
        };
        
        const data = await SaisieFetch(action, bodyParams);
        if (data !== null) { 
            const successMessage = (action === 'add_saisie') ? 'Donnée ajoutée avec succès.' : 'Donnée mise à jour avec succès.';
            showStatusMessage(successMessage, false); 
            setFormToNewMode();
            await refreshSaisiesTable();
            await loadUtilisateurs();
        }
    };
    
    window.editSaisie = async (button) => {
        const tr = button.closest('tr');
        const data = JSON.parse(tr.dataset.saisieData);

        currentFournisseurId = data.ID_Fournisseur;
        if (fournisseurSelect) fournisseurSelect.value = data.ID_Fournisseur;

        enableForm(true);
        
        saisieIdField.value = data.ID;
        saisieFormTitle.textContent = `Modifier la donnée #${data.ID}`;
        submitSaisieForm.innerHTML = '<i class="fas fa-save mr-1"></i> Mettre à jour';
        
        categorieSelect.value = data.ID_Categorie;
        
        const categorieCodeMatch = data.CategorieLibelle ? data.CategorieLibelle.match(/^(\w+) -/) : null;
        const categorieCode = categorieCodeMatch ? categorieCodeMatch[1] : '';
        const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B');

        let sousCatIdToLoad = data.ID_Sous_Categorie;
        
        if (HAS_SOUS_CATEGORIE) {
            if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'flex';
            if (sousCategorieSelect) sousCategorieSelect.required = true;

            if (isDechet4B) {
                if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'none';
                if (sousCategorieSelect) sousCategorieSelect.required = false;
                sousCatIdToLoad = 0;
            } else {
                await loadSousCategories(data.ID_Categorie, sousCategorieSelect);
                if (sousCategorieSelect) {
                    sousCategorieSelect.value = data.ID_Sous_Categorie;
                    sousCategorieSelect.disabled = false;
                }
            }
        }

        await loadSourcesEmission(data.ID_Categorie, sousCatIdToLoad);
        
        sourceEmissionSelect.value = data.ID_Source_Emission;
        sourceEmissionSelect.disabled = false;

        if (HAS_COMBUSTIBLE && typeCombustibleSelect) {
            typeCombustibleSelect.value = data.Type_Combustible || "";
        }
        if (personneContact) personneContact.value = data.Personne_Contact || "";
        
        // Remplir DA
        if (disponibiliteDa) disponibiliteDa.value = data.Disponibilite_DA || "";
        valeurSaisie1.value = data.Donnee_sur_activite || "";
        valeurSaisie2.value = data.Valeur_Donnee || ""; 
        if (anneeProductionDa) anneeProductionDa.value = data.Annee_Production_DA || ""; 
        uniteMesureSelect.value = data.Unite_mesure || ''; 
        if (incertitudeDa) incertitudeDa.value = data.Incertitude_DA || "";
        
        // Remplir FE
        if (gesSelect) gesSelect.value = data.GES || "";
        if (valeurFe) valeurFe.value = data.Valeur_FE || "";
        if (uniteFe) uniteFe.value = data.Unite_FE || "";
        if (incertitudeFe) incertitudeFe.value = data.Incertitude_FE || "";

        commentaireFournisseur.value = data.Commentaire_fournisseur || "";
        if(commentaireValidateur) commentaireValidateur.value = data.Commentaire_validateur || "";
        if(validationCheckbox) validationCheckbox.checked = (data.Validation == 1);
        
        checkEnergieCombustible();
        saisieForm.scrollIntoView({ behavior: 'smooth' });
    };

    window.deleteSaisie = (id) => {
        if (!CAN_DELETE) return; 
        showDeleteModal(id);
    };

    // --- 6. Fonctions de Lot (Validation) ---
    
    const getSelectedIds = () => {
        const checkboxes = document.querySelectorAll('.saisie-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.dataset.id);
    };

    const updateBatchButtons = () => {
        if (!CAN_VALIDATE) return; 
        const selectedCount = getSelectedIds().length;
        if (validateSelected) validateSelected.disabled = (selectedCount === 0);
        if (invalidateSelected) invalidateSelected.disabled = (selectedCount === 0);
    };
    
    const handleBatchValidate = async (type) => { 
        const ids = getSelectedIds();
        if (ids.length === 0) {
            showStatusMessage("Veuillez sélectionner au moins une ligne.", true);
            return;
        }
        
        const data = await SaisieFetch('batch_validate', { ids: ids, type: type });
        if (data !== null) {
            showStatusMessage(data.message || `${ids.length} donnée(s) mise(s) à jour.`, false);
            await refreshSaisiesTable();
        }
    };

    // ===========================================
    // --- 7. ÉCOUTEURS D'ÉVÉNEMENTS ET INITIALISATION ---
    // ===========================================
    
    saisieForm.addEventListener('submit', handleFormSubmit);
    clearSaisieFormBtn.addEventListener('click', setFormToNewMode);

    if (deleteModal) {
        cancelDeleteBtn.addEventListener('click', hideDeleteModal);
        closeDeleteModalBtn.addEventListener('click', hideDeleteModal);
        window.addEventListener('click', (event) => {
            if (event.target == deleteModal) hideDeleteModal();
        });

        confirmDeleteBtn.addEventListener('click', async () => {
            if (currentSaisieIdToDelete === null) return;
            const idToDelete = currentSaisieIdToDelete;
            hideDeleteModal(); 
            
            const data = await SaisieFetch('delete_saisie', { id: idToDelete });
            if (data !== null) {
                showStatusMessage('Donnée supprimée avec succès.', false);
                if (saisieIdField.value == idToDelete) setFormToNewMode(); 
                await refreshSaisiesTable();
                await loadUtilisateurs();
            }
        });
    }

    if (fournisseurSelect) {
        fournisseurSelect.addEventListener('change', (e) => { 
            currentFournisseurId = parseInt(e.target.value) || 0;
        });
    }

    categorieSelect.addEventListener('change', async (e) => {
        const catId = e.target.value;
        const categorieCode = getSelectedCategoryCode();
        const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B'); 

        resetSelect(sourceEmissionSelect, '-- Sélectionner (Catégorie/Sous-Cat.) --');
        sourceEmissionSelect.disabled = true;

        if (HAS_SOUS_CATEGORIE) {
            if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'flex'; 
            if (sousCategorieSelect) sousCategorieSelect.required = true;

            resetSelect(sousCategorieSelect, '-- Sélectionner d\'abord une catégorie --');
            
            if (catId > 0) {
                if (isDechet4B) {
                    if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'none';
                    if (sousCategorieSelect) sousCategorieSelect.required = false;

                    await loadSourcesEmission(catId, 0); 
                    sourceEmissionSelect.disabled = false;
                } else {
                    await loadSousCategories(catId, sousCategorieSelect);
                    if (sousCategorieSelect) sousCategorieSelect.disabled = false;
                }
            } else {
                if (sousCategorieSelect) sousCategorieSelect.disabled = true;
            }
        } else {
            if (catId > 0) {
                await loadSourcesEmission(catId, 0);
                sourceEmissionSelect.disabled = false;
            }
        }
        checkEnergieCombustible();
    });

    if(HAS_SOUS_CATEGORIE && sousCategorieSelect) {
        sousCategorieSelect.addEventListener('change', async (e) => {
            const sousCatId = e.target.value;
            const catId = categorieSelect.value;
            resetSelect(sourceEmissionSelect, '-- Sélectionner une sous-catégorie --');
            
            const categorieCode = getSelectedCategoryCode();
            const isDechet4B = (SECTEUR_ID === 4 && categorieCode === '4B');
            
            if (isDechet4B) return; 

            if (sousCatId > 0 && catId > 0) {
                await loadSourcesEmission(catId, sousCatId);
                sourceEmissionSelect.disabled = false;
            } else {
                sourceEmissionSelect.disabled = true;
            }
        });
    }

    if (checkAllSaisies) {
        checkAllSaisies.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            document.querySelectorAll('.saisie-checkbox').forEach(cb => cb.checked = isChecked);
            updateBatchButtons();
        });
    }
    saisiesTableBody.addEventListener('change', (e) => {
        if (e.target.classList.contains('saisie-checkbox')) updateBatchButtons();
    });

    if (filterFournisseurSelect) filterFournisseurSelect.addEventListener('change', refreshSaisiesTable);
    
    if (filterCategorieSelect) {
        filterCategorieSelect.addEventListener('change', async () => {
            const catId = filterCategorieSelect.value;
            if (HAS_SOUS_CATEGORIE && filterSousCategorieSelect) {
                if (catId > 0) {
                    await loadSousCategories(catId, filterSousCategorieSelect);
                    filterSousCategorieSelect.disabled = false;
                } else {
                    resetSelect(filterSousCategorieSelect, '-- Toutes les sous-catégories --');
                    filterSousCategorieSelect.disabled = true;
                }
            }
            await refreshSaisiesTable();
        });
    }
    
    if (filterSousCategorieSelect) filterSousCategorieSelect.addEventListener('change', refreshSaisiesTable);
    if (filterUtilisateurSelect) filterUtilisateurSelect.addEventListener('change', refreshSaisiesTable);

    // ===========================================
    // --- 8. INITIALISATION AU CHARGEMENT ---
    // ===========================================
    
    if (HAS_SOUS_CATEGORIE) sousCategorieSelect.disabled = true;
    sourceEmissionSelect.disabled = true;

    await loadInitialData(); 

    if (currentFournisseurId > 0) {
        if(fournisseurSelect) fournisseurSelect.value = currentFournisseurId;
    }
    
    await loadCategories(); 
    await loadUtilisateurs(); 

    enableForm(true); 
    
    if(CAN_VALIDATE) {
        if(validateSelected) validateSelected.addEventListener('click', () => handleBatchValidate('valider'));
        if(invalidateSelected) invalidateSelected.addEventListener('click', () => handleBatchValidate('invalider'));
    }

    await refreshSaisiesTable(); 
    checkEnergieCombustible(); 
    
})();
</script>