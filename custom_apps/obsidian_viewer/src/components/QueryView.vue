<template>
	<div class="query-view">
		<div class="query-view__form">
			<div class="query-view__row">
				<label class="query-view__label">Fields</label>
				<div class="query-view__field-chips">
					<button v-for="f in availableFields"
						:key="f"
						class="query-view__chip"
						:class="{ 'query-view__chip--active': selectedFields.includes(f) }"
						@click="toggleField(f)">
						{{ f }}
					</button>
				</div>
			</div>
			<div class="query-view__row">
				<label class="query-view__label">Filter</label>
				<input v-model="filter"
					class="query-view__input"
					type="text"
					placeholder='e.g. tag:project AND ["status":active]'
					@keydown.enter="runQuery">
			</div>
			<div class="query-view__row query-view__row--inline">
				<div class="query-view__sort-group">
					<label class="query-view__label">Sort</label>
					<select v-model="sortField" class="query-view__select">
						<option value="">None</option>
						<option v-for="f in selectedFields" :key="f" :value="f">{{ f }}</option>
					</select>
					<button v-if="sortField"
						class="query-view__sort-dir"
						:title="sortDesc ? 'Descending' : 'Ascending'"
						@click="sortDesc = !sortDesc">
						{{ sortDesc ? '↓' : '↑' }}
					</button>
				</div>
				<div class="query-view__limit-group">
					<label class="query-view__label">Limit</label>
					<input v-model.number="limit"
						class="query-view__input query-view__input--small"
						type="number"
						min="1"
						max="500">
				</div>
				<button class="query-view__run" @click="runQuery" :disabled="selectedFields.length === 0 || running">
					{{ running ? 'Running...' : 'Run query' }}
				</button>
			</div>
		</div>

		<div v-if="error" class="query-view__error">{{ error }}</div>

		<div v-if="rows !== null" class="query-view__results">
			<div class="query-view__stats">{{ rows.length }} result{{ rows.length !== 1 ? 's' : '' }}</div>
			<div class="query-view__table-wrap">
				<table class="query-view__table">
					<thead>
						<tr>
							<th v-for="f in resultFields" :key="f"
								class="query-view__th"
								@click="resortBy(f)">
								{{ f }}
								<span v-if="sortField === f" class="query-view__sort-indicator">{{ sortDesc ? '▼' : '▲' }}</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(row, i) in rows" :key="i"
							class="query-view__tr"
							@click="onRowClick(row)">
							<td v-for="f in resultFields" :key="f" class="query-view__td">
								<template v-if="Array.isArray(row[f])">
									<span v-for="(tag, j) in row[f]" :key="j" class="query-view__tag">{{ tag }}</span>
								</template>
								<template v-else>{{ formatCell(f, row[f]) }}</template>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div v-else-if="!running" class="query-view__empty">
			Select fields and run a query to see results.
		</div>
	</div>
</template>

<script>
import { queryVault } from '../services/api.js'

const BUILTIN_FIELDS = ['file.name', 'file.path', 'file.ext', 'file.mtime', 'tags', 'links']

export default {
	name: 'QueryView',

	props: {
		vaultName: { type: String, required: true },
		properties: { type: Array, default: () => [] },
	},

	emits: ['navigate'],

	data() {
		return {
			selectedFields: ['file.name', 'tags', 'file.mtime'],
			filter: '',
			sortField: 'file.name',
			sortDesc: false,
			limit: 100,
			rows: null,
			resultFields: [],
			running: false,
			error: null,
		}
	},

	computed: {
		availableFields() {
			const extra = this.properties.filter((p) => !BUILTIN_FIELDS.includes(p))
			return [...BUILTIN_FIELDS, ...extra]
		},
	},

	methods: {
		toggleField(f) {
			const idx = this.selectedFields.indexOf(f)
			if (idx >= 0) {
				this.selectedFields.splice(idx, 1)
				if (this.sortField === f) this.sortField = ''
			} else {
				this.selectedFields.push(f)
			}
		},

		async runQuery() {
			if (this.selectedFields.length === 0) return
			this.running = true
			this.error = null
			try {
				const sort = this.sortField ? (this.sortDesc ? '-' : '') + this.sortField : undefined
				const result = await queryVault(this.vaultName, {
					fields: this.selectedFields,
					filter: this.filter || undefined,
					sort,
					limit: this.limit,
				})
				this.resultFields = result.fields
				this.rows = result.rows
				if (result.error) {
					this.error = result.error
				}
			} catch (e) {
				this.error = e.response?.data?.error || e.message
				this.rows = null
			} finally {
				this.running = false
			}
		},

		resortBy(field) {
			if (this.sortField === field) {
				this.sortDesc = !this.sortDesc
			} else {
				this.sortField = field
				this.sortDesc = false
			}
			this.runQuery()
		},

		formatCell(field, value) {
			if (value === null || value === undefined) return '—'
			if (field === 'file.mtime') {
				return new Date(value * 1000).toLocaleString()
			}
			return String(value)
		},

		onRowClick(row) {
			const path = row['file.path']
			if (path) {
				this.$emit('navigate', path)
			}
		},
	},
}
</script>

<style scoped>
.query-view {
	height: 100%;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.query-view__form {
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.query-view__row {
	margin-bottom: 8px;
}

.query-view__row--inline {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	flex-wrap: wrap;
}

.query-view__label {
	display: block;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.query-view__field-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.query-view__chip {
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: transparent;
	font-size: 12px;
	cursor: pointer;
	color: var(--color-main-text);
	transition: background 0.15s, border-color 0.15s;
}

.query-view__chip:hover {
	background: var(--color-background-hover);
}

.query-view__chip--active {
	background: var(--color-primary-element-light);
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.query-view__input {
	width: 100%;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
	font-family: inherit;
}

.query-view__input--small {
	width: 70px;
}

.query-view__select {
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
}

.query-view__sort-group,
.query-view__limit-group {
	display: flex;
	flex-direction: column;
}

.query-view__sort-group {
	flex-direction: row;
	align-items: flex-end;
	gap: 4px;
}

.query-view__sort-group .query-view__label {
	position: absolute;
	top: -14px;
}

.query-view__sort-group {
	position: relative;
	padding-top: 14px;
}

.query-view__sort-dir {
	width: 30px;
	height: 30px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: transparent;
	font-size: 14px;
	cursor: pointer;
	color: var(--color-main-text);
}

.query-view__run {
	padding: 6px 16px;
	border: none;
	border-radius: 6px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
	margin-left: auto;
}

.query-view__run:hover {
	background: var(--color-primary-element-hover);
}

.query-view__run:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.query-view__error {
	padding: 8px 16px;
	background: var(--color-error);
	color: white;
	font-size: 13px;
}

.query-view__results {
	flex: 1;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.query-view__stats {
	padding: 6px 16px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.query-view__table-wrap {
	flex: 1;
	overflow: auto;
}

.query-view__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.query-view__th {
	position: sticky;
	top: 0;
	background: var(--color-main-background);
	padding: 6px 12px;
	text-align: left;
	font-weight: 600;
	font-size: 12px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	border-bottom: 2px solid var(--color-border);
	cursor: pointer;
	white-space: nowrap;
	user-select: none;
}

.query-view__th:hover {
	color: var(--color-main-text);
}

.query-view__sort-indicator {
	font-size: 10px;
	margin-left: 4px;
}

.query-view__tr {
	cursor: pointer;
	transition: background 0.1s;
}

.query-view__tr:hover {
	background: var(--color-background-hover);
}

.query-view__td {
	padding: 6px 12px;
	border-bottom: 1px solid var(--color-border-dark, var(--color-border));
	vertical-align: top;
}

.query-view__tag {
	display: inline-block;
	padding: 1px 6px;
	margin: 1px 2px;
	border-radius: 10px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-size: 11px;
}

.query-view__empty {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}
</style>
