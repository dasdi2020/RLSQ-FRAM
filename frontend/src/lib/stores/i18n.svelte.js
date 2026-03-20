/**
 * Store i18n complet FR/EN/ES — toutes les traductions de l'application.
 */

let currentLang = $state(localStorage.getItem('lang') || 'fr');

const translations = {
    fr: {
        // Sidebar
        dashboard: 'Dashboard', database: 'Base de données', erd: 'Éditeur ERD',
        modules: 'Modules', forms: 'Formulaires', pages: 'Pages', embeds: 'Embeds',
        users: 'Utilisateurs', audit: 'Audit', notifications: 'Notifications',
        settings: 'Paramètres', system: 'Système', tools: 'Outils',
        platform: 'Plateforme d\'administration',

        // Auth
        logout: 'Déconnexion', profile: 'Profil', login: 'Connexion',
        email: 'Email', password: 'Mot de passe', login_btn: 'Se connecter',
        connecting: 'Connexion en cours...', verify_code: 'Vérifier le code',
        verifying: 'Vérification...', code_sent: 'Code de vérification envoyé par email',
        connect_account: 'Connectez-vous à votre compte',
        enter_code: 'Un code à 6 chiffres a été envoyé à',
        back_login: 'Retour à la connexion', mfa_default: 'Double authentification activée par défaut',

        // Profile
        my_profile: 'Mon profil', personal_info: 'Informations personnelles',
        first_name: 'Prénom', last_name: 'Nom', save: 'Sauvegarder', saving: 'Sauvegarde...',
        change_password: 'Changer le mot de passe', current_password: 'Mot de passe actuel',
        new_password: 'Nouveau mot de passe', confirm_password: 'Confirmer',
        security: 'Sécurité', mfa_title: 'Authentification multi-facteur (MFA)',
        mfa_email: 'Par email', mfa_totp: 'Par application (TOTP)',
        mfa_email_desc: 'Code envoyé par email à chaque connexion',
        mfa_totp_desc: 'Google Authenticator, Microsoft Authenticator, Authy...',
        mfa_configure: 'Configurer', mfa_active: 'Actif', mfa_switch: 'Utiliser cette méthode',
        totp_scan: 'Scannez ce QR code avec votre application d\'authentification',
        totp_manual: 'Ou entrez ce code manuellement :',
        totp_verify: 'Entrez le code affiché dans l\'application pour confirmer',
        totp_confirm: 'Confirmer l\'activation', sessions: 'Sessions actives',
        one_session: '1 session',

        // Users
        invite: 'Inviter', invite_user: 'Inviter un utilisateur',
        invite_desc: 'Un email d\'invitation sera envoyé avec un lien de connexion.',
        send_invite: 'Envoyer l\'invitation', role: 'Rôle', roles: 'Rôles',
        super_admin: 'Super Admin', admin: 'Administrateur', user: 'Utilisateur',
        super_admin_desc: 'Accès total à la plateforme',
        admin_desc: 'Gestion des tenants et utilisateurs', user_desc: 'Accès standard',
        status: 'Statut', registered: 'Inscrit le',

        // Dashboard
        welcome: 'Bienvenue', organizations: 'Organisations', available_modules: 'Modules disponibles',
        environment: 'Environnement', get_started: 'Commencez par :',
        create_tables: 'Créez vos tables visuellement',
        install_modules: 'Installez formations, paiements...',
        build_forms: 'Construisez des formulaires',

        // Schema
        tables: 'Tables', new_table: 'Nouvelle table', tech_name: 'Nom technique',
        display_name: 'Nom d\'affichage', create: 'Créer', cancel: 'Annuler',
        delete: 'Supprimer', fields: 'Champs', relations: 'Relations',
        add_field: '+ Champ', add_relation: '+ Relation',
        choose_category: 'Choisir une catégorie', choose_type: 'Choisir le type',
        configure_field: 'Configurer le champ', what_data: 'Quel type de données voulez-vous stocker ?',
        default_value: 'Valeur par défaut', nullable: 'Nullable', unique: 'Unique',
        indexed: 'Indexé', add_the_field: 'Ajouter le champ', max_length: 'Longueur max',

        // Common
        loading: 'Chargement...', no_data: 'Aucune donnée', confirm: 'Confirmer',
        close: 'Fermer', edit: 'Modifier', actions: 'Actions', export: 'Exporter',
        search: 'Rechercher', filter: 'Filtrer', yes: 'Oui', no: 'Non',
        active: 'Actif', inactive: 'Inactif', enabled: 'Activé', disabled: 'Désactivé',
    },

    en: {
        dashboard: 'Dashboard', database: 'Database', erd: 'ERD Editor',
        modules: 'Modules', forms: 'Forms', pages: 'Pages', embeds: 'Embeds',
        users: 'Users', audit: 'Audit', notifications: 'Notifications',
        settings: 'Settings', system: 'System', tools: 'Tools',
        platform: 'Admin Platform',

        logout: 'Logout', profile: 'Profile', login: 'Login',
        email: 'Email', password: 'Password', login_btn: 'Sign in',
        connecting: 'Connecting...', verify_code: 'Verify code',
        verifying: 'Verifying...', code_sent: 'Verification code sent by email',
        connect_account: 'Sign in to your account',
        enter_code: 'A 6-digit code was sent to',
        back_login: 'Back to login', mfa_default: 'Two-factor authentication enabled by default',

        my_profile: 'My Profile', personal_info: 'Personal Information',
        first_name: 'First name', last_name: 'Last name', save: 'Save', saving: 'Saving...',
        change_password: 'Change password', current_password: 'Current password',
        new_password: 'New password', confirm_password: 'Confirm',
        security: 'Security', mfa_title: 'Multi-factor Authentication (MFA)',
        mfa_email: 'By email', mfa_totp: 'By app (TOTP)',
        mfa_email_desc: 'Code sent by email on each login',
        mfa_totp_desc: 'Google Authenticator, Microsoft Authenticator, Authy...',
        mfa_configure: 'Configure', mfa_active: 'Active', mfa_switch: 'Use this method',
        totp_scan: 'Scan this QR code with your authenticator app',
        totp_manual: 'Or enter this code manually:',
        totp_verify: 'Enter the code shown in the app to confirm',
        totp_confirm: 'Confirm activation', sessions: 'Active sessions',
        one_session: '1 session',

        invite: 'Invite', invite_user: 'Invite a user',
        invite_desc: 'An invitation email will be sent with a login link.',
        send_invite: 'Send invitation', role: 'Role', roles: 'Roles',
        super_admin: 'Super Admin', admin: 'Administrator', user: 'User',
        super_admin_desc: 'Full platform access',
        admin_desc: 'Tenant and user management', user_desc: 'Standard access',
        status: 'Status', registered: 'Registered',

        welcome: 'Welcome', organizations: 'Organizations', available_modules: 'Available modules',
        environment: 'Environment', get_started: 'Get started:',
        create_tables: 'Create your tables visually',
        install_modules: 'Install training, payments...',
        build_forms: 'Build forms',

        tables: 'Tables', new_table: 'New table', tech_name: 'Technical name',
        display_name: 'Display name', create: 'Create', cancel: 'Cancel',
        delete: 'Delete', fields: 'Fields', relations: 'Relations',
        add_field: '+ Field', add_relation: '+ Relation',
        choose_category: 'Choose a category', choose_type: 'Choose the type',
        configure_field: 'Configure field', what_data: 'What kind of data do you want to store?',
        default_value: 'Default value', nullable: 'Nullable', unique: 'Unique',
        indexed: 'Indexed', add_the_field: 'Add field', max_length: 'Max length',

        loading: 'Loading...', no_data: 'No data', confirm: 'Confirm',
        close: 'Close', edit: 'Edit', actions: 'Actions', export: 'Export',
        search: 'Search', filter: 'Filter', yes: 'Yes', no: 'No',
        active: 'Active', inactive: 'Inactive', enabled: 'Enabled', disabled: 'Disabled',
    },

    es: {
        dashboard: 'Panel', database: 'Base de datos', erd: 'Editor ERD',
        modules: 'Módulos', forms: 'Formularios', pages: 'Páginas', embeds: 'Embeds',
        users: 'Usuarios', audit: 'Auditoría', notifications: 'Notificaciones',
        settings: 'Configuración', system: 'Sistema', tools: 'Herramientas',
        platform: 'Plataforma de administración',

        logout: 'Cerrar sesión', profile: 'Perfil', login: 'Iniciar sesión',
        email: 'Correo electrónico', password: 'Contraseña', login_btn: 'Iniciar sesión',
        connecting: 'Conectando...', verify_code: 'Verificar código',
        verifying: 'Verificando...', code_sent: 'Código de verificación enviado por correo',
        connect_account: 'Inicie sesión en su cuenta',
        enter_code: 'Se envió un código de 6 dígitos a',
        back_login: 'Volver al inicio de sesión', mfa_default: 'Autenticación de dos factores activada por defecto',

        my_profile: 'Mi perfil', personal_info: 'Información personal',
        first_name: 'Nombre', last_name: 'Apellido', save: 'Guardar', saving: 'Guardando...',
        change_password: 'Cambiar contraseña', current_password: 'Contraseña actual',
        new_password: 'Nueva contraseña', confirm_password: 'Confirmar',
        security: 'Seguridad', mfa_title: 'Autenticación multifactor (MFA)',
        mfa_email: 'Por correo', mfa_totp: 'Por aplicación (TOTP)',
        mfa_email_desc: 'Código enviado por correo en cada inicio de sesión',
        mfa_totp_desc: 'Google Authenticator, Microsoft Authenticator, Authy...',
        mfa_configure: 'Configurar', mfa_active: 'Activo', mfa_switch: 'Usar este método',
        totp_scan: 'Escanee este código QR con su aplicación de autenticación',
        totp_manual: 'O ingrese este código manualmente:',
        totp_verify: 'Ingrese el código mostrado en la aplicación para confirmar',
        totp_confirm: 'Confirmar activación', sessions: 'Sesiones activas',
        one_session: '1 sesión',

        invite: 'Invitar', invite_user: 'Invitar un usuario',
        invite_desc: 'Se enviará un correo de invitación con un enlace de inicio de sesión.',
        send_invite: 'Enviar invitación', role: 'Rol', roles: 'Roles',
        super_admin: 'Super Admin', admin: 'Administrador', user: 'Usuario',
        super_admin_desc: 'Acceso total a la plataforma',
        admin_desc: 'Gestión de tenants y usuarios', user_desc: 'Acceso estándar',
        status: 'Estado', registered: 'Registrado',

        welcome: 'Bienvenido', organizations: 'Organizaciones', available_modules: 'Módulos disponibles',
        environment: 'Entorno', get_started: 'Comience con:',
        create_tables: 'Cree sus tablas visualmente',
        install_modules: 'Instale formaciones, pagos...',
        build_forms: 'Construya formularios',

        tables: 'Tablas', new_table: 'Nueva tabla', tech_name: 'Nombre técnico',
        display_name: 'Nombre de visualización', create: 'Crear', cancel: 'Cancelar',
        delete: 'Eliminar', fields: 'Campos', relations: 'Relaciones',
        add_field: '+ Campo', add_relation: '+ Relación',
        choose_category: 'Elegir una categoría', choose_type: 'Elegir el tipo',
        configure_field: 'Configurar campo', what_data: '¿Qué tipo de datos desea almacenar?',
        default_value: 'Valor por defecto', nullable: 'Nulable', unique: 'Único',
        indexed: 'Indexado', add_the_field: 'Agregar campo', max_length: 'Longitud máxima',

        loading: 'Cargando...', no_data: 'Sin datos', confirm: 'Confirmar',
        close: 'Cerrar', edit: 'Editar', actions: 'Acciones', export: 'Exportar',
        search: 'Buscar', filter: 'Filtrar', yes: 'Sí', no: 'No',
        active: 'Activo', inactive: 'Inactivo', enabled: 'Activado', disabled: 'Desactivado',
    },
};

export function getI18n() {
    return {
        get lang() { return currentLang; },
        set lang(v) { currentLang = v; localStorage.setItem('lang', v); },
        get t() { return translations[currentLang] || translations.fr; },
        tr(key) { return (translations[currentLang] || translations.fr)[key] || key; },
        get availableLanguages() { return [
            { code: 'fr', label: 'Français', flag: '🇫🇷' },
            { code: 'en', label: 'English', flag: '🇬🇧' },
            { code: 'es', label: 'Español', flag: '🇪🇸' },
        ]; },
    };
}
