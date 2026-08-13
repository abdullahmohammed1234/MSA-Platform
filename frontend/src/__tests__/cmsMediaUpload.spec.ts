import { describe, it, expect, vi } from 'vitest';
import {
  CMS_MEDIA_MAX_DOCUMENT_KB,
  CMS_MEDIA_MAX_IMAGE_KB,
  CMS_MEDIA_MAX_VIDEO_KB,
  getCmsMediaFileKind,
  validateCmsMediaFileSize,
} from '@/constants/cmsMediaUpload';

function makeFile(name: string, sizeBytes: number, type: string): File {
  const file = new File(['x'], name, { type });
  Object.defineProperty(file, 'size', { value: sizeBytes });
  return file;
}

describe('cmsMediaUpload limits', () => {
  it('matches backend default kilobyte limits', () => {
    expect(CMS_MEDIA_MAX_IMAGE_KB).toBe(10240);
    expect(CMS_MEDIA_MAX_VIDEO_KB).toBe(51200);
    expect(CMS_MEDIA_MAX_DOCUMENT_KB).toBe(10240);
  });

  it('classifies image, video, and document files', () => {
    expect(getCmsMediaFileKind(makeFile('photo.jpg', 100, 'image/jpeg'))).toBe('image');
    expect(getCmsMediaFileKind(makeFile('clip.mp4', 100, 'video/mp4'))).toBe('video');
    expect(getCmsMediaFileKind(makeFile('guide.pdf', 100, 'application/pdf'))).toBe('document');
  });
});

describe('validateCmsMediaFileSize', () => {
  it('allows a valid video under the configured limit so upload can proceed', () => {
    const underLimitBytes = (CMS_MEDIA_MAX_VIDEO_KB - 1) * 1024;
    const file = makeFile('welcome.mp4', underLimitBytes, 'video/mp4');

    expect(validateCmsMediaFileSize(file)).toBeNull();
  });

  it('rejects a video over the configured limit before any API request', () => {
    const overLimitBytes = (CMS_MEDIA_MAX_VIDEO_KB + 1) * 1024;
    const file = makeFile('huge.mp4', overLimitBytes, 'video/mp4');

    const error = validateCmsMediaFileSize(file);

    expect(error).toContain('video');
    expect(error).toContain(String(CMS_MEDIA_MAX_VIDEO_KB));
  });

  it('keeps existing image size behavior aligned with the image limit', () => {
    const validImage = makeFile('photo.jpg', (CMS_MEDIA_MAX_IMAGE_KB - 1) * 1024, 'image/jpeg');
    const oversizedImage = makeFile('photo.jpg', (CMS_MEDIA_MAX_IMAGE_KB + 1) * 1024, 'image/jpeg');

    expect(validateCmsMediaFileSize(validImage)).toBeNull();
    expect(validateCmsMediaFileSize(oversizedImage)).toContain('image');
    expect(validateCmsMediaFileSize(oversizedImage)).toContain(String(CMS_MEDIA_MAX_IMAGE_KB));
  });

  it('keeps existing document size behavior aligned with the document limit', () => {
    const validDoc = makeFile('notes.pdf', (CMS_MEDIA_MAX_DOCUMENT_KB - 1) * 1024, 'application/pdf');
    const oversizedDoc = makeFile('notes.pdf', (CMS_MEDIA_MAX_DOCUMENT_KB + 1) * 1024, 'application/pdf');

    expect(validateCmsMediaFileSize(validDoc)).toBeNull();
    expect(validateCmsMediaFileSize(oversizedDoc)).toContain('document');
    expect(validateCmsMediaFileSize(oversizedDoc)).toContain(String(CMS_MEDIA_MAX_DOCUMENT_KB));
  });
});

describe('MediaCms upload gate', () => {
  it('does not call uploadMedia when client-side video size validation fails', async () => {
    const uploadMedia = vi.fn();
    const file = makeFile('huge.mp4', (CMS_MEDIA_MAX_VIDEO_KB + 1) * 1024, 'video/mp4');

    const sizeError = validateCmsMediaFileSize(file);
    expect(sizeError).not.toBeNull();

    if (!sizeError) {
      await uploadMedia(file);
    }

    expect(uploadMedia).not.toHaveBeenCalled();
  });

  it('calls uploadMedia when a video is within the configured limit', async () => {
    const uploadMedia = vi.fn().mockResolvedValue({ uuid: 'ok' });
    const file = makeFile('ok.mp4', (CMS_MEDIA_MAX_VIDEO_KB - 1) * 1024, 'video/mp4');

    const sizeError = validateCmsMediaFileSize(file);
    expect(sizeError).toBeNull();

    if (!sizeError) {
      await uploadMedia(file);
    }

    expect(uploadMedia).toHaveBeenCalledTimes(1);
    expect(uploadMedia).toHaveBeenCalledWith(file);
  });
});
