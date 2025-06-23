# Claude Code Preferences

### General Context

-   This is a Laravel 12 api project

### Laravel

-   Always use api routes in routes/api.php, this is an api.
-   Do not create requests, resources or tests unless asked.

#### Where to find Laravel files

-   Models can be found in app/Models/{Model}
-   Controllers can be found in app/Http/{ModelController}
-   Requests can be found in app/Http/Requests/{Model}/{ModelActionRequest}
-   Resources can be found in app/Http/Resources/{Model}/{ModelResource}
-   LLM agent tools can be found in app/Tools
-   Feature tests can be found in tests/Feature/{Model}/{ModelTest}

#### Controller rules

-   Always return json responses from controllers, this is an api.

#### General Laravel rules

-   Always use implicit route model binding in controllers when possible.
-   Always look at existing related tests as reference if you are having trouble writing a new test.
-   Never run php artisan serve.

### Vue Rules

-   Never write typescript. If you see typescript, do not remove it just leave it, unless instructed to remove it.
-   Always write Pinia store in composition api setup style not option api style.
-   Always use script setup style in Vue components.
-   Always attempt to use a component in resources/js/components for components you can use before writing new components.
-   Always attempt to use a ui element in resources/js/components/ui before writing new ui elements such as buttons, cards and form elements.
-   Always import components then layouts after all other imports.
-   Always use '@' for imports.

### Tailwind Rules

-   Always use Tailwind CSS classes for styling.
-   Always use shades of bg-neutral for backgrounds.
