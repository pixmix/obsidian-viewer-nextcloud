import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export async function fetchVaults() {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults')
	const response = await axios.get(url)
	return response.data.vaults
}

export async function fetchFiles(vaultName) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/files', { vault: vaultName })
	const response = await axios.get(url)
	return response.data.tree
}

export async function fetchGraph(vaultName, { mdLinks = false } = {}) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/graph', { vault: vaultName })
	const params = {}
	if (mdLinks) params.mdLinks = '1'
	const response = await axios.get(url, { params })
	return response.data
}

export async function fetchProperties(vaultName) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/properties', { vault: vaultName })
	const response = await axios.get(url)
	return response.data.properties
}

export async function fetchSearch(vaultName, query) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/search', { vault: vaultName })
	const response = await axios.get(url, { params: { q: query } })
	return response.data.results
}

export function getRawFileUrl(vaultName, fileName) {
	return generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/raw', { vault: vaultName })
		+ '?name=' + encodeURIComponent(fileName)
}

export async function queryVault(vaultName, { fields, filter, sort, limit }) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/query', { vault: vaultName })
	const response = await axios.post(url, { fields, filter, sort, limit })
	return response.data
}

export async function fetchFileInfo(vaultName, fileName) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/fileinfo', { vault: vaultName })
	const response = await axios.get(url, { params: { name: fileName } })
	return response.data
}

export async function fetchFileContent(vaultName, filePath) {
	const url = generateUrl('/apps/obsidian_viewer/api/vaults/{vault}/file', { vault: vaultName })
	const response = await axios.get(url, { params: { path: filePath } })
	return response.data.content
}
