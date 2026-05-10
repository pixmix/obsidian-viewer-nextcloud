<template>
	<div class="search-hints">
		<template v-if="!showProperties">
			<div class="search-hints__title">
				<span>Search options</span>
			</div>
			<div v-for="op in operators"
				:key="op.prefix"
				class="search-hints__row"
				@mousedown.prevent="onOperatorClick(op)">
				<strong>{{ op.label }}</strong> {{ op.description }}
			</div>
		</template>
		<template v-else>
			<div class="search-hints__title search-hints__title--back"
				@mousedown.prevent="showProperties = false">
				<span>&larr; Properties</span>
			</div>
			<div v-if="properties.length === 0" class="search-hints__empty">
				No properties found
			</div>
			<div v-for="prop in properties"
				:key="prop"
				class="search-hints__row"
				@mousedown.prevent="selectProperty(prop)">
				{{ prop }}
			</div>
		</template>
	</div>
</template>

<script>
export default {
	name: 'SearchHints',

	props: {
		properties: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['select'],

	data() {
		return {
			showProperties: false,
			operators: [
				{ prefix: 'path:', label: 'path:', description: 'match path of the file' },
				{ prefix: 'file:', label: 'file:', description: 'match file name' },
				{ prefix: 'tag:', label: 'tag:', description: 'search for tags' },
				{ prefix: 'line:', label: 'line:', description: 'search keywords on same line' },
				{ prefix: 'section:', label: 'section:', description: 'search keywords under same heading' },
				{ prefix: '[property]', label: '[property]', description: 'match property' },
			],
		}
	},

	methods: {
		onOperatorClick(op) {
			if (op.prefix === '[property]') {
				this.showProperties = true
			} else {
				this.$emit('select', op.prefix)
			}
		},

		selectProperty(prop) {
			this.$emit('select', '["' + prop + '":]')
		},
	},
}
</script>

<style scoped>
.search-hints {
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-main-background);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
	overflow: hidden;
}

.search-hints__title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 12px;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	border-bottom: 1px solid var(--color-border);
}

.search-hints__title--back {
	cursor: pointer;
}

.search-hints__title--back:hover {
	color: var(--color-main-text);
}

.search-hints__row {
	padding: 6px 12px;
	font-size: 13px;
	cursor: pointer;
}

.search-hints__row:hover {
	background: var(--color-background-hover);
}

.search-hints__row strong {
	color: var(--color-primary-element);
}

.search-hints__empty {
	padding: 12px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}
</style>
