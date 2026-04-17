@if (!empty($nodes))
    <ul class="employee-org-tree">
        @foreach ($nodes as $node)
            @php
                $hasChildren = !empty($node['children']);
            @endphp
            <li class="employee-org-tree-item {{ $hasChildren ? 'has-children' : '' }}"
                data-org-unit-id="{{ (int) $node['id'] }}">
                <div class="employee-org-tree-row">
                    @if ($hasChildren)
                        <button type="button" class="employee-org-tree-toggle" aria-label="{{ localize('toggle_children') }}"
                            aria-expanded="false">
                            <span class="toggle-symbol">+</span>
                        </button>
                    @else
                        <span class="employee-org-tree-toggle-placeholder"></span>
                    @endif

                    <a href="#" class="employee-org-tree-node-filter" data-org-unit-id="{{ (int) $node['id'] }}">
                        @if (!is_null($node['sort_order'] ?? null))
                            <span class="employee-org-tree-order">
                                {{ str_pad((string) ((int) $node['sort_order']), 2, '0', STR_PAD_LEFT) }}
                            </span>
                        @endif
                        <i class="fa fa-folder employee-org-tree-icon" aria-hidden="true"></i>
                        <span class="employee-org-tree-name">{{ $node['name'] }}</span>
                        <span class="employee-org-tree-type">- {{ $node['type'] }}</span>
                    </a>
                </div>

                @if ($hasChildren)
                    @include('humanresource::employee.partials.filter-org-tree', ['nodes' => $node['children']])
                @endif
            </li>
        @endforeach
    </ul>
@endif

