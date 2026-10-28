import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_ui/kindo_ui.dart';

void main() {
  test('two askers for the same picture share one download, and the answer comes back', () async {
    var calls = 0;
    final cache = MediaCache(ApiClient(baseUrl: 'https://k.test/api/v1', client: MockClient((r) async {
      calls++;
      await Future<void>.delayed(const Duration(milliseconds: 20));
      return http.Response.bytes([9, 9], 200);
    })));
    final both = await Future.wait([cache.bytes('https://k.test/api/v1/media/5/image?s=1', version: 'a'), cache.bytes('https://k.test/api/v1/media/5/image?s=2', version: 'a')]);
    expect(both.map((b) => b?.length), [2, 2]);
    expect(calls, 1, reason: 'the same file under a new signature is the same picture');
    expect(cache.has('https://k.test/api/v1/media/5/image?s=3', 'a'), isTrue);
    expect(cache.has('https://k.test/api/v1/media/5/image?s=3', 'b'), isFalse, reason: 'a replaced file has a new version');
  });

  test('a failed download is null, not a crash, and can be tried again', () async {
    var up = false;
    final cache = MediaCache(ApiClient(baseUrl: 'https://k.test/api/v1', client: MockClient((r) async => up ? http.Response.bytes([1], 200) : http.Response('no', 500))));
    expect(await cache.bytes('https://k.test/api/v1/media/6/image?s=1'), isNull);
    up = true;
    expect(await cache.bytes('https://k.test/api/v1/media/6/image?s=1'), isNotNull);
  });

  test('prefetch downloads every item, one after the other', () async {
    final asked = <String>[];
    final cache = MediaCache(ApiClient(baseUrl: 'https://k.test/api/v1', client: MockClient((r) async {
      asked.add(r.url.path);
      return http.Response.bytes([1, 2, 3], 200);
    })));
    await cache.prefetch([
      (url: 'https://k.test/api/v1/media/1/image?sig=a', version: 'v1'),
      (url: 'https://k.test/api/v1/media/2/image?sig=b', version: 'v1'),
      (url: 'https://k.test/api/v1/media/2/audio?sig=b', version: null),
    ]);
    expect(asked, ['/api/v1/media/1/image', '/api/v1/media/2/image', '/api/v1/media/2/audio']);
  });
}
