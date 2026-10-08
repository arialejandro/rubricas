<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Product;
use App\Models\Project;
use App\Support\Campos;
use App\Support\Level;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Producto de un proyecto + su instrumento de evaluación (criterios a observar con el
 * descriptor de cada nivel). El campo del producto decide a qué campo formativo suma.
 */
class ProductController extends Controller
{
    public const MAX_CRITERIA = 8;

    public function create(Group $group, Project $project): View
    {
        $product = new Product(['campo' => $project->campo]);
        $product->setRelation('criteria', collect());

        return view('products.form', compact('group', 'project', 'product'));
    }

    public function store(Request $request, Group $group, Project $project): RedirectResponse
    {
        [$attrs, $criteria] = $this->validated($request);

        DB::transaction(function () use ($project, $attrs, $criteria) {
            $product = $project->products()->create([...$attrs, 'position' => $project->products()->count()]);
            foreach ($criteria as $i => $c) {
                $product->criteria()->create([...$c, 'position' => $i]);
            }
        });

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Producto agregado.');
    }

    public function edit(Group $group, Project $project, Product $product): View
    {
        $product->load(['criteria' => fn ($q) => $q->withCount('scores')]);

        return view('products.form', compact('group', 'project', 'product'));
    }

    public function update(Request $request, Group $group, Project $project, Product $product): RedirectResponse
    {
        [$attrs, $criteria] = $this->validated($request);

        $keep = collect($criteria)->pluck('id')->filter();
        $removed = $product->criteria()->whereNotIn('id', $keep)->withCount('scores')->get();
        $graded = $removed->where('scores_count', '>', 0);
        if ($graded->isNotEmpty() && ! $request->boolean('confirm_remove')) {
            throw ValidationException::withMessages([
                'confirm_remove' => 'Vas a quitar criterios que ya tienen calificaciones. Marca la casilla de confirmación para borrarlas.',
            ]);
        }

        DB::transaction(function () use ($product, $attrs, $criteria, $removed) {
            $product->update($attrs);
            $removed->each->delete();
            foreach ($criteria as $i => $c) {
                $existing = ! empty($c['id']) ? $product->criteria()->find($c['id']) : null;
                $fields = [...collect($c)->except('id')->all(), 'position' => $i];
                $existing ? $existing->update($fields) : $product->criteria()->create($fields);
            }
        });

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Producto actualizado.');
    }

    public function destroy(Request $request, Group $group, Project $project, Product $product): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirma que quieres eliminar el producto y sus calificaciones.']);
        $product->delete();

        return redirect()->route('projects.show', [$group, $project])->with('status', 'Producto eliminado.');
    }

    /** @return array{0: array, 1: list<array>} */
    private function validated(Request $request): array
    {
        $levels = collect(array_keys(Level::LEVELS))->map(fn ($l) => Level::column($l));
        $request->merge(['criteria' => collect($request->input('criteria', []))
            ->filter(fn ($c) => trim($c['description'] ?? '') !== '')->values()->all()]);

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'campo' => ['required', Rule::in(Campos::keys())],
            'instrument' => ['nullable', 'string', 'max:80'],
            'criteria' => ['required', 'array', 'min:1', 'max:'.self::MAX_CRITERIA],
            'criteria.*.id' => ['nullable', 'integer'],
            'criteria.*.description' => ['required', 'string', 'max:255'],
        ];
        foreach ($levels as $col) {
            $rules["criteria.*.{$col}"] = ['nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules, [
            'criteria.required' => 'Agrega al menos un criterio a observar.',
            'criteria.max' => 'Máximo '.self::MAX_CRITERIA.' criterios por producto.',
        ]);

        $criteria = collect($data['criteria'])->map(fn ($c) => collect($c)->only(['id', 'description', ...$levels])->all())->all();

        return [collect($data)->only(['name', 'campo', 'instrument'])->all(), $criteria];
    }
}
