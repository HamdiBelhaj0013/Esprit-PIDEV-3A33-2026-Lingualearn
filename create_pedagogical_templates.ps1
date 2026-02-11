$dirs = @(
    "templates\pedagogical_content\course",
    "templates\pedagogical_content\language",
    "templates\pedagogical_content\lesson"
)

foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

$courseIndex = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Gestion des cours{% endblock %}

{% block page_title %}Gestion des cours{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="#">Contenu Pédagogique</a>
        </li>
        <li class="breadcrumb-item active">
            <a href="{{ path('admin_course_index') }}">Cours</a>
        </li>
    </ol>
{% endblock %}

{% block page_actions %}
    <div class="col-auto">
        <a href="{{ path('admin_course_new') }}" class="btn btn-primary">
            <i class="ti ti-plus"></i> Créer un cours
        </a>
    </div>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Langue</th>
                            <th class="w-25">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for course in courses %}
                            <tr>
                                <td>
                                    <span class="text-muted">{{ course.id }}</span>
                                </td>
                                <td>{{ course.title }}</td>
                                <td>
                                    <span class="badge bg-blue">{{ course.language.name }}</span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ path('admin_course_show', {'id': course.id}) }}" class="btn btn-icon btn-sm btn-info" title="Voir">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ path('admin_course_edit', {'id': course.id}) }}" class="btn btn-icon btn-sm btn-warning" title="Modifier">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                        <form method="post" action="{{ path('admin_course_delete', {'id': course.id}) }}" style="display:inline;" id="deleteForm{{ course.id }}">
                                            <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ course.id) }}">
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-form-id="deleteForm{{ course.id }}" title="Supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        {% else %}
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="ti ti-inbox"></i> Aucun cours trouvé.
                                </td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\course\index.html.twig" -Value $courseIndex -Encoding UTF8

$courseNew = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Créer un cours{% endblock %}

{% block page_title %}Créer un cours{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_course_index') }}">Cours</a>
        </li>
        <li class="breadcrumb-item active">Créer un cours</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.title, 'Titre', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.title, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.title) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.description, 'Description', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.description, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.description) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.language, 'Langue', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.language, {'attr': {'class': 'form-select'}}) }}
                        {{ form_errors(form.language) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Créer le cours
                        </button>
                        <a href="{{ path('admin_course_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\course\new.html.twig" -Value $courseNew -Encoding UTF8

$courseEdit = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Modifier le cours{% endblock %}

{% block page_title %}Modifier le cours{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_course_index') }}">Cours</a>
        </li>
        <li class="breadcrumb-item active">{{ course.title }}</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.title, 'Titre', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.title, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.title) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.description, 'Description', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.description, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.description) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.language, 'Langue', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.language, {'attr': {'class': 'form-select'}}) }}
                        {{ form_errors(form.language) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Enregistrer
                        </button>
                        <a href="{{ path('admin_course_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\course\edit.html.twig" -Value $courseEdit -Encoding UTF8

# Create Language templates
$languageIndex = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Gestion des langues{% endblock %}

{% block page_title %}Gestion des langues{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="#">Contenu Pédagogique</a>
        </li>
        <li class="breadcrumb-item active">
            <a href="{{ path('admin_language_index') }}">Langues</a>
        </li>
    </ol>
{% endblock %}

{% block page_actions %}
    <div class="col-auto">
        <a href="{{ path('admin_language_new') }}" class="btn btn-primary">
            <i class="ti ti-plus"></i> Créer une langue
        </a>
    </div>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Code</th>
                            <th class="w-25">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for language in languages %}
                            <tr>
                                <td>
                                    <span class="text-muted">{{ language.id }}</span>
                                </td>
                                <td>{{ language.name }}</td>
                                <td>
                                    <span class="badge bg-blue">{{ language.code }}</span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ path('admin_language_show', {'id': language.id}) }}" class="btn btn-icon btn-sm btn-info" title="Voir">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ path('admin_language_edit', {'id': language.id}) }}" class="btn btn-icon btn-sm btn-warning" title="Modifier">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                        <form method="post" action="{{ path('admin_language_delete', {'id': language.id}) }}" style="display:inline;" id="deleteForm{{ language.id }}">
                                            <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ language.id) }}">
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-form-id="deleteForm{{ language.id }}" title="Supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        {% else %}
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="ti ti-inbox"></i> Aucune langue trouvée.
                                </td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\language\index.html.twig" -Value $languageIndex -Encoding UTF8

$languageNew = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Créer une langue{% endblock %}

{% block page_title %}Créer une langue{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_language_index') }}">Langues</a>
        </li>
        <li class="breadcrumb-item active">Créer une langue</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.name, 'Nom', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.name, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.name) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.code, 'Code ISO', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.code, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.code) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Créer la langue
                        </button>
                        <a href="{{ path('admin_language_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\language\new.html.twig" -Value $languageNew -Encoding UTF8

$languageEdit = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Modifier la langue{% endblock %}

{% block page_title %}Modifier la langue{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_language_index') }}">Langues</a>
        </li>
        <li class="breadcrumb-item active">{{ language.name }}</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.name, 'Nom', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.name, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.name) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.code, 'Code ISO', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.code, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.code) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Enregistrer
                        </button>
                        <a href="{{ path('admin_language_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\language\edit.html.twig" -Value $languageEdit -Encoding UTF8

# Create Lesson templates
$lessonIndex = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Gestion des leçons{% endblock %}

{% block page_title %}Gestion des leçons{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="#">Contenu Pédagogique</a>
        </li>
        <li class="breadcrumb-item active">
            <a href="{{ path('admin_lesson_index') }}">Leçons</a>
        </li>
    </ol>
{% endblock %}

{% block page_actions %}
    <div class="col-auto">
        <a href="{{ path('admin_lesson_new') }}" class="btn btn-primary">
            <i class="ti ti-plus"></i> Créer une leçon
        </a>
    </div>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Cours</th>
                            <th>Récompense XP</th>
                            <th class="w-25">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for lesson in lessons %}
                            <tr>
                                <td>
                                    <span class="text-muted">{{ lesson.id }}</span>
                                </td>
                                <td>{{ lesson.title }}</td>
                                <td>
                                    {% if lesson.course %}
                                        <span class="badge bg-blue">{{ lesson.course.title }}</span>
                                    {% else %}
                                        <span class="text-muted">N/A</span>
                                    {% endif %}
                                </td>
                                <td>
                                    <span class="badge bg-yellow">{{ lesson.xpReward }} XP</span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ path('admin_lesson_show', {'id': lesson.id}) }}" class="btn btn-icon btn-sm btn-info" title="Voir">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ path('admin_lesson_edit', {'id': lesson.id}) }}" class="btn btn-icon btn-sm btn-warning" title="Modifier">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                        <form method="post" action="{{ path('admin_lesson_delete', {'id': lesson.id}) }}" style="display:inline;" id="deleteForm{{ lesson.id }}">
                                            <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ lesson.id) }}">
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-form-id="deleteForm{{ lesson.id }}" title="Supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        {% else %}
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="ti ti-inbox"></i> Aucune leçon trouvée.
                                </td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\lesson\index.html.twig" -Value $lessonIndex -Encoding UTF8

$lessonNew = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Créer une leçon{% endblock %}

{% block page_title %}Créer une leçon{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_lesson_index') }}">Leçons</a>
        </li>
        <li class="breadcrumb-item active">Créer une leçon</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.title, 'Titre', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.title, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.title) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.content, 'Contenu', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.content, {'attr': {'class': 'form-control', 'rows': '8'}}) }}
                        {{ form_errors(form.content) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.course, 'Cours associé', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.course, {'attr': {'class': 'form-select'}}) }}
                        {{ form_errors(form.course) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.xpReward, 'Récompense XP', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.xpReward, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.xpReward) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.vocabularyData, 'Données de vocabulaire (JSON)', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.vocabularyData, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.vocabularyData) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.grammarData, 'Données de grammaire (JSON)', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.grammarData, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.grammarData) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Créer la leçon
                        </button>
                        <a href="{{ path('admin_lesson_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\lesson\new.html.twig" -Value $lessonNew -Encoding UTF8

$lessonEdit = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · Modifier la leçon{% endblock %}

{% block page_title %}Modifier la leçon{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_lesson_index') }}">Leçons</a>
        </li>
        <li class="breadcrumb-item active">{{ lesson.title }}</li>
    </ol>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                {{ form_start(form, {'attr': {'class': 'form-horizontal'}}) }}
                    <div class="mb-3">
                        {{ form_label(form.title, 'Titre', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.title, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.title) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.content, 'Contenu', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.content, {'attr': {'class': 'form-control', 'rows': '8'}}) }}
                        {{ form_errors(form.content) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.course, 'Cours associé', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.course, {'attr': {'class': 'form-select'}}) }}
                        {{ form_errors(form.course) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.xpReward, 'Récompense XP', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.xpReward, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(form.xpReward) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.vocabularyData, 'Données de vocabulaire (JSON)', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.vocabularyData, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.vocabularyData) }}
                    </div>

                    <div class="mb-3">
                        {{ form_label(form.grammarData, 'Données de grammaire (JSON)', {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(form.grammarData, {'attr': {'class': 'form-control', 'rows': '5'}}) }}
                        {{ form_errors(form.grammarData) }}
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> Enregistrer
                        </button>
                        <a href="{{ path('admin_lesson_index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Retour
                        </a>
                    </div>
                {{ form_end(form) }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\lesson\edit.html.twig" -Value $lessonEdit -Encoding UTF8

$lessonShow = @'
{% extends 'base.html.twig' %}

{% block title %}Admin · {{ lesson.title }}{% endblock %}

{% block page_title %}{{ lesson.title }}{% endblock %}

{% block breadcrumb %}
    <ol class="breadcrumb breadcrumb-icons" aria-label="breadcrumbs">
        <li class="breadcrumb-item">
            <a href="{{ path('admin_dashboard') }}">
                <i class="ti ti-home"></i> Tableau de bord
            </a>
        </li>
        <li class="breadcrumb-item">Contenu Pédagogique</li>
        <li class="breadcrumb-item">
            <a href="{{ path('admin_lesson_index') }}">Leçons</a>
        </li>
        <li class="breadcrumb-item active">{{ lesson.title }}</li>
    </ol>
{% endblock %}

{% block page_actions %}
    <div class="col-auto">
        <div class="btn-group">
            <a href="{{ path('admin_lesson_edit', {'id': lesson.id}) }}" class="btn btn-primary">
                <i class="ti ti-edit"></i> Modifier
            </a>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-form-id="deleteForm{{ lesson.id }}">
                <i class="ti ti-trash"></i> Supprimer
            </button>
        </div>
    </div>
{% endblock %}

{% block body %}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="mb-4">
                    <h4 class="text-muted mb-2">Titre</h4>
                    <p class="mb-0">{{ lesson.title }}</p>
                </div>

                <div class="mb-4">
                    <h4 class="text-muted mb-2">Cours</h4>
                    <p class="mb-0">
                        {% if lesson.course %}
                            <span class="badge bg-blue">{{ lesson.course.title }}</span>
                        {% else %}
                            <span class="text-muted">N/A</span>
                        {% endif %}
                    </p>
                </div>

                <div class="mb-4">
                    <h4 class="text-muted mb-2">Contenu</h4>
                    <div class="border p-3 rounded" style="max-height: 300px; overflow-y: auto;">
                        {{ lesson.content|nl2br }}
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h4 class="text-muted mb-2">Récompense XP</h4>
                            <p class="mb-0">
                                <span class="badge bg-yellow">{{ lesson.xpReward }} XP</span>
                            </p>
                        </div>
                    </div>
                </div>

                {% if lesson.vocabularyData|length > 0 %}
                    <div class="mb-4">
                        <h4 class="text-muted mb-2">Vocabulaire</h4>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                {% for vocab in lesson.vocabularyData %}
                                    <tr>
                                        <td><strong>{{ vocab.word ?? 'N/A' }}</strong></td>
                                        <td>{{ vocab.translation ?? 'N/A' }}</td>
                                    </tr>
                                {% endfor %}
                            </table>
                        </div>
                    </div>
                {% endif %}

                {% if lesson.grammarData|length > 0 %}
                    <div class="mb-4">
                        <h4 class="text-muted mb-2">Grammaire</h4>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                {% for grammar in lesson.grammarData %}
                                    <tr>
                                        <td><strong>{{ grammar.rule ?? 'N/A' }}</strong></td>
                                        <td>{{ grammar.example ?? 'N/A' }}</td>
                                    </tr>
                                {% endfor %}
                            </table>
                        </div>
                    </div>
                {% endif %}
            </div>
        </div>
    </div>
</div>

<form method="post" action="{{ path('admin_lesson_delete', {'id': lesson.id}) }}" style="display:none;" id="deleteForm{{ lesson.id }}">
    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ lesson.id) }}">
</form>
{% endblock %}
'@

Set-Content -Path "templates\pedagogical_content\lesson\show.html.twig" -Value $lessonShow -Encoding UTF8

# Verify all files were created
Write-Host "Files created successfully!" -ForegroundColor Green
Get-ChildItem -Path "templates\pedagogical_content\" -Recurse -Filter "*.twig" | Select-Object FullName