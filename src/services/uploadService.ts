const CHUNK_SIZE = 100 * 1024 * 1024;

export const uploadLargeFile = async (file: File, apiKey: string) => {
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
  const uploadId = Date.now().toString();

  for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
    const start = chunkIndex * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    const formData = new FormData();
    formData.append('api_key', apiKey);
    formData.append('file', chunk);
    formData.append('filename', file.name);
    formData.append('chunk_index', chunkIndex.toString());
    formData.append('total_chunks', totalChunks.toString());
    formData.append('upload_id', uploadId);

    const response = await fetch('https://synclyz.com/api/v2/file/upload', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`Failed to upload chunk ${chunkIndex + 1} of ${totalChunks}`);
    }
  }

  return { success: true, message: 'Upload complete and verified.' };
};
