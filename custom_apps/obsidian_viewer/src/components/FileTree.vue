<template>
	<ul class="file-tree">
		<li v-for="node in tree" :key="node.path">
			<template v-if="node.type === 'folder'">
				<NcAppNavigationItem :name="node.name"
					:allow-collapse="true"
					:open="isOpen(node.path)"
					@update:open="toggleFolder(node.path, $event)">
					<template #icon>
						<FolderIcon :size="20" />
					</template>
					<template #default>
						<FileTree :tree="node.children || []"
							:selected-path="selectedPath"
							@select="$emit('select', $event)" />
					</template>
				</NcAppNavigationItem>
			</template>
			<template v-else>
				<NcAppNavigationItem :name="fileDisplayName(node.name)"
					:active="selectedPath === node.path"
					@click="$emit('select', node.path)">
					<template #icon>
						<FileDocumentIcon v-if="node.name.endsWith('.md')" :size="20" />
						<CanvasIcon v-else-if="node.name.endsWith('.canvas')" :size="20" />
						<ImageIcon v-else-if="isImage(node.name)" :size="20" />
						<FileIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</li>
	</ul>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import CanvasIcon from 'vue-material-design-icons/DrawPen.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import FileIcon from 'vue-material-design-icons/File.vue'

const IMAGE_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'ico']

export default {
	name: 'FileTree',

	components: {
		NcAppNavigationItem,
		FolderIcon,
		FileDocumentIcon,
		CanvasIcon,
		ImageIcon,
		FileIcon,
	},

	props: {
		tree: {
			type: Array,
			required: true,
		},
		selectedPath: {
			type: String,
			default: null,
		},
	},

	emits: ['select'],

	data() {
		return {
			openFolders: {},
		}
	},

	methods: {
		isOpen(path) {
			return this.openFolders[path] === true
		},

		toggleFolder(path, open) {
			this.$set(this.openFolders, path, open)
		},

		fileDisplayName(name) {
			if (name.endsWith('.md') || name.endsWith('.canvas')) {
				return name.replace(/\.(md|canvas)$/, '')
			}
			return name
		},

		isImage(name) {
			const ext = name.split('.').pop().toLowerCase()
			return IMAGE_EXTS.includes(ext)
		},
	},
}
</script>

<style scoped>
.file-tree {
	list-style: none;
	padding: 0;
	margin: 0;
}
</style>
