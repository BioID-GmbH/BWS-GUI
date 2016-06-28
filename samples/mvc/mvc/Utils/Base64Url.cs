using System;

namespace mvc.Utils
{
    /// <summary>
    /// Special Base 64 Data Encoding according to RFC 4648 Section 5, referred
    /// to as "base64url": 'Base 64 Encoding with URL and Filename Safe Alphabet'
    /// </summary>
    public static class Base64Url
    {
        /// <summary>
        /// Encodes a byte array into its equivalent string representation using
        /// base 64 digits, which is usable for transmission on the URL.
        /// </summary>
        /// <param name="input">The byte array to encode.</param>
        /// <returns>The "base64url" encoded string.</returns>
        public static string Encode(byte[] input)
        {
            string s = Convert.ToBase64String(input); // Standard base64 encoder
            s = s.TrimEnd('='); // Remove any trailing '='s
            s = s.Replace('+', '-'); // 62nd char of encoding
            s = s.Replace('/', '_'); // 63rd char of encoding
            return s;
        }

        /// <summary>
        /// Decodes a "base64url" encoded string to its equivalent byte array.
        /// </summary>
        /// <param name="input">The "base64url" encoded string to decode.</param>
        /// <returns>The byte array containing the decoded string.</returns>
        public static byte[] Decode(string input)
        {
            string s = input.Replace('-', '+').Replace('_', '/'); // 62nd and 63rd char of encoding
            s = s.PadRight(s.Length + (4 - s.Length % 4) % 4, '='); // Pad with trailing '='s
            return Convert.FromBase64String(s); // Standard base64 decoder
        }
    }
}
